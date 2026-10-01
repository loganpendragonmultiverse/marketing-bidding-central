#!/usr/bin/env python3
"""Check release source for private hosting residue and version consistency."""
import json, pathlib, re, sys, subprocess
root = pathlib.Path(__file__).resolve().parents[1]
errors = []
blocked = ['logan'+'pendragon'+'forge', 'part of the '+'network', 'lab-'+'feedback.php', 'contact-'+'intake.php', 'google'+'tagmanager.com', 'G-'+'N02RLBH8VH', 'h1bj'+'2bj5qzzz', '/srv/'+'forgeos']
ignore = {'.git', 'vendor', 'node_modules', 'dist', '.phpunit.cache', '__pycache__'}
paths=subprocess.check_output(['git','ls-files','-co','--exclude-standard','-z'],cwd=root).decode().split('\0')
for p in (root/x for x in set(paths) if x):
    if not p.is_file() or any(x in ignore for x in p.relative_to(root).parts):
        continue
    rel = p.relative_to(root).as_posix()
    if rel.startswith('storage/') or rel.startswith('bootstrap/cache/'):
        if p.name != '.gitignore':
            continue
    if (p.name.startswith('.env') and p.name != '.env.example') or p.suffix in {'.sqlite','.sql','.gz','.log'}:
        errors.append(rel+': runtime/private file')
    try: data=p.read_text()
    except UnicodeDecodeError: continue
    folded=data.lower()
    for value in blocked:
        if value.lower() in folded: errors.append(rel+': hosted residue')
    if re.search(r'(?:sk|rk)_(?:live|test)_[A-Za-z0-9]{16,}|whsec_[A-Za-z0-9]{16,}',data):
        errors.append(rel+': provider credential')
version=(root/'VERSION').read_text().strip()
if json.loads((root/'composer.json').read_text()).get('version') != version: errors.append('Composer version mismatch')
if "'version' => '"+version+"'" not in (root/'config/marketplace.php').read_text(): errors.append('Runtime version mismatch')
if 'v'+version not in (root/'README.md').read_text() or '## '+version not in (root/'CHANGELOG.md').read_text(): errors.append('Release documentation mismatch')
if errors:
    print('\n'.join(errors)); sys.exit(1)
print('Release privacy/version audit passed')
