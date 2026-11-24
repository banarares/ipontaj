#!/usr/bin/env python3
"""Exercise release checks using disposable files and fabricated credentials."""
import json
import subprocess
import sys
import tempfile
import unittest
from pathlib import Path

SCANNER = Path(__file__).with_name('check-secrets.py').resolve()


class ReleaseSecretChecks(unittest.TestCase):
    def setUp(self):
        self.directory = tempfile.TemporaryDirectory()
        self.addCleanup(self.directory.cleanup)
        self.root = Path(self.directory.name)
        subprocess.run(['git', 'init', '-q', str(self.root)], check=True)

    def stage(self, path, content):
        file = self.root / path
        file.parent.mkdir(parents=True, exist_ok=True)
        file.write_bytes(content)
        subprocess.run(['git', '-C', str(self.root), 'add', '-f', '--', path], check=True)

    def scan(self, *args):
        process = subprocess.run([sys.executable, str(SCANNER), '--root', str(self.root), *args], capture_output=True, text=True)
        return process.returncode, json.loads(process.stdout), process.stdout

    def test_empty_example_configuration_is_allowed(self):
        self.stage('.env.example', b'APP_KEY=\nSTRIPE_SECRET=\nDB_PASSWORD=\n')
        self.assertEqual(self.scan('--staged')[0], 0)

    def test_staged_secret_is_detected_even_after_working_file_is_cleaned(self):
        credential = b'sk_' + b'test_' + b'A' * 24
        self.stage('config/example.php', credential)
        (self.root / 'config/example.php').write_text('safe working tree')
        code, result, output = self.scan('--staged')
        self.assertEqual(code, 1)
        self.assertEqual(result['findings'][0]['rule'], 'stripe-key')
        self.assertNotIn(credential.decode(), output)
        self.assertEqual(self.scan()[0], 0)

    def test_private_tooling_database_and_env_are_rejected(self):
        for path in ['.local-tools/backup.py', 'backup/current.sql', '.env', 'database/live.sqlite', 'storage/logs/app.log']:
            self.stage(path, b'private')
        code, result, _ = self.scan('--staged')
        self.assertEqual(code, 1)
        self.assertEqual(len(result['findings']), 5)
        self.assertTrue(all(item['rule'] == 'private-or-runtime-path' for item in result['findings']))

    def test_configured_secret_is_detected_in_a_binary_without_exposure(self):
        credential = 'synthetic-' + 'configuration-value-' + 'x' * 16
        (self.root / 'private.env').write_text('APP_KEY=' + credential)
        self.stage('public/example.png', b'\x00' + credential.encode())
        code, result, output = self.scan('--staged', '--known-secrets-from', str(self.root / 'private.env'))
        self.assertEqual(code, 1)
        self.assertEqual(result['findings'][0]['rule'], 'configured-secret')
        self.assertNotIn(credential, output)

    def test_example_secret_must_be_empty(self):
        self.stage('.env.example', b'DB_PASSWORD=synthetic-not-for-publication\n')
        code, result, _ = self.scan('--staged')
        self.assertEqual(code, 1)
        self.assertEqual(result['findings'][0]['rule'], 'nonempty-example-secret')

    def test_turnstile_secret_key_is_checked_without_exposing_its_value(self):
        credential = 'synthetic-turnstile-' + 'server-value-' + 'x' * 12
        (self.root / 'private.env').write_text('TURNSTILE_SECRET_KEY=' + credential)
        self.stage('config/example.php', credential.encode())
        code, result, output = self.scan('--staged', '--known-secrets-from', str(self.root / 'private.env'))
        self.assertEqual(code, 1)
        self.assertEqual(result['findings'][0]['rule'], 'configured-secret')
        self.assertNotIn(credential, output)


if __name__ == '__main__':
    unittest.main()
