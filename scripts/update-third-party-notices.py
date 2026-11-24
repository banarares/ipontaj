#!/usr/bin/env python3
"""Regenerate dependency notices from locked, locally installed dependencies."""
import json
import re
import shutil
from pathlib import Path
root = Path(__file__).resolve().parents[1]
destination = root / 'resources/legal/third-party'
if destination.exists():
    shutil.rmtree(destination)
destination.mkdir(parents=True)
records = []
def record(ecosystem, name, version, licenses, directory):
    entry = {'ecosystem': ecosystem, 'name': name, 'version': version, 'licenses': licenses or ['Not declared in lockfile'], 'notices': []}
    if directory.is_dir():
        for file in directory.iterdir():
            if file.is_file() and re.match(r'^(LICENSE|LICENCE|COPYING|NOTICE|OFL)([.\-_].*)?$', file.name, re.I):
                relative = Path(ecosystem) / name / file.name
                target = destination / relative
                target.parent.mkdir(parents=True, exist_ok=True)
                shutil.copyfile(file, target)
                entry['notices'].append(relative.as_posix())
    records.append(entry)
composer = json.loads((root / 'composer.lock').read_text())
for package in composer['packages'] + composer.get('packages-dev', []):
    record('composer', package['name'], package['version'], package.get('license', []), root / 'vendor' / package['name'])
lock = json.loads((root / 'package-lock.json').read_text())
for location, package in lock['packages'].items():
    if not location or 'node_modules/' not in location:
        continue
    name = location.rsplit('node_modules/', 1)[1]
    licenses = package.get('license', '')
    record('npm', name, package.get('version', ''), [licenses] if licenses else [], root / location)
(destination / 'inventory.json').write_text(json.dumps(records, indent=2, ensure_ascii=False) + '\n')
(root / 'THIRD_PARTY_NOTICES.md').write_text('''# Third-party notices

The original iPontaj code is licensed under AGPL-3.0-only. Dependencies retain
their own licenses. The complete locked inventory and available original license
and notice texts are included under `resources/legal/third-party/` and in the
source archive offered at `/cod-sursa`.

Regenerate after dependency updates using `python3 scripts/update-third-party-notices.py`
with Composer and npm dependencies installed. Both production and development
packages are inventoried; this does not relicense those packages under AGPL.

Inter font files: SIL Open Font License. Lucide icons: ISC. Laravel: MIT.
Consult the individual preserved license files and package manifests for details.

The official ANPC SAL pictogram is an institutional information asset, not an
approval badge. It was retrieved from
https://www.anpc.ro/download/sal/SAL-PICTOGRAMA.png on 2026-11-24 through the
public page https://www.anpc.ro/public/sal. Original dimensions: 201 × 50 px.
The site presents it in a 250 × 50 px image box with `object-fit: contain`,
preserving the original artwork and proportions. Link: https://reclamatiisal.anpc.ro.
The institutional asset is not relicensed under AGPL.

Additional authority logos are served locally as informational links, with the
original artwork preserved: ANPC from
https://www.anpc.ro/uploads/settings/01KPQVAXAYJA6YRBCFTERW7MME.png
and ANSPDCP from https://www.dataprotection.ro/servlet/ViewImage?id=1722,
retrieved from the respective official homepages on 2026-11-24. Their presence
does not state certification or institutional approval of iPontaj. These
institutional marks are not relicensed under AGPL.
''')
print(f'Inventoried {len(records)} locked dependencies; preserved original license files.')
