#!/usr/bin/env python3
"""Scan tracked/staged release files without printing matched secret values."""
import argparse
import json
import re
import subprocess
from pathlib import Path

PATTERNS = {
    'stripe-key': re.compile(rb'(?:sk|rk|pk)_(?:live|test)_[A-Za-z0-9]{16,}'),
    'stripe-webhook': re.compile(rb'whsec_[A-Za-z0-9]{20,}'),
    'github-token': re.compile(rb'(?:gh[pousr]_[A-Za-z0-9]{24,}|github_pat_[A-Za-z0-9_]{30,})'),
    'aws-access-key': re.compile(rb'(?:AKIA|ASIA)[A-Z0-9]{16}'),
    'private-key': re.compile(rb'-----BEGIN (?:[A-Z0-9]+ )?PRIVATE KEY-----'),
    'credential-in-url': re.compile(rb'https?://[^\s/:@]+:[^\s/@]+@'),
    'slack-token': re.compile(rb'xox[baprs]-[A-Za-z0-9-]{20,}'),
    'npm-token': re.compile(rb'npm_[A-Za-z0-9]{30,}'),
}
SECRET_ENV = re.compile(r'(?:PASSWORD|SECRET|SECRET_KEY|TOKEN|ACCESS_KEY|API_KEY|APP_KEY|STRIPE_KEY)$')


def git(root, *args):
    return subprocess.check_output(['git', '-C', str(root), *args], stderr=subprocess.DEVNULL)


def forbidden(path):
    p = Path(path)
    parts = p.parts
    if p.is_absolute() or '..' in parts:
        return True
    if p.name.startswith('.env') and p.name != '.env.example':
        return True
    # Laravel's Blade overrides live in resources/views/vendor; these are app source,
    # while installed dependency trees remain private. Content checks still apply.
    view_override = parts[:3] == ('resources', 'views', 'vendor') and path.endswith('.blade.php')
    if 'vendor' in parts and not view_override:
        return True
    if any(x in parts for x in ['.runtime', '.local-tools', 'backup', '.aws', '.codex', '.agents', 'node_modules', '.git']):
        return True
    if p.name in ['auth.json', 'public/hot', 'config.php.bak']:
        return True
    if path == 'public/hot' or path.startswith('public/build/'):
        return True
    if (path.startswith('storage/') or path.startswith('bootstrap/cache/')) and p.name != '.gitignore':
        return True
    return p.suffix.lower() in ['.log', '.sqlite', '.sqlite3', '.db', '.sql', '.dump', '.bak', '.zip', '.xlsx', '.csv', '.pem', '.p12', '.pfx', '.key']


def known_secrets(path):
    if not path:
        return []
    values = []
    for line in Path(path).read_text().splitlines():
        if '=' not in line or line.lstrip().startswith('#'):
            continue
        name, value = line.split('=', 1)
        value = value.strip().strip('\"\'')
        # Documented public Cloudflare dummy keys are test fixtures, not credentials.
        public_turnstile_test_key = value in [prefix + 'x' + '0' * 31 + 'AA' for prefix in ['1', '2', '3']]
        if SECRET_ENV.search(name.strip()) and len(value) >= 12 and '${' not in value and not public_turnstile_test_key:
            values.append(value.encode())
    return values


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--root', default='.')
    parser.add_argument('--staged', action='store_true')
    parser.add_argument('--files-from', help='JSON list of relative release paths, instead of git ls-files')
    parser.add_argument('--known-secrets-from', help='Optional local env file; values are never printed')
    args = parser.parse_args()
    root = Path(args.root).resolve()
    try:
        paths = json.loads(Path(args.files_from).read_text()) if args.files_from else [p.decode() for p in git(root, 'ls-files', '-z').split(b'\0') if p]
        secrets = known_secrets(args.known_secrets_from)
    except (OSError, subprocess.CalledProcessError, ValueError):
        print('Cannot read the file list. Run inside a Git checkout or provide --files-from.')
        return 2
    findings = []
    scanned = 0
    for path in paths:
        if forbidden(path):
            findings.append({'path': path, 'rule': 'private-or-runtime-path'})
            continue
        try:
            file = root / path
            if file.is_symlink() or not file.resolve().is_relative_to(root):
                findings.append({'path': path, 'rule': 'symlink-or-outside-root'})
                continue
            if not args.staged and not file.exists():
                continue  # A tracked file removed from the working tree.
            body = git(root, 'show', ':' + path) if args.staged else file.read_bytes()
            scanned += 1
        except (OSError, subprocess.CalledProcessError):
            findings.append({'path': path, 'rule': 'unreadable-file'})
            continue
        for value in secrets:
            if value in body:
                findings.append({'path': path, 'rule': 'configured-secret'})
                break
        if b'\0' in body:
            continue
        for name, pattern in PATTERNS.items():
            if pattern.search(body):
                findings.append({'path': path, 'rule': name})
        if Path(path).name == '.env.example':
            for line in body.decode('utf-8').splitlines():
                if '=' not in line or line.lstrip().startswith('#'):
                    continue
                name, value = line.split('=', 1)
                if SECRET_ENV.search(name.strip()) and value.strip().strip('\"\'') not in ['', 'null', '${APP_NAME}']:
                    findings.append({'path': path, 'rule': 'nonempty-example-secret'})
    print(json.dumps({'files_scanned': scanned, 'findings': findings}, ensure_ascii=False))
    return 1 if findings else 0


if __name__ == '__main__':
    raise SystemExit(main())
