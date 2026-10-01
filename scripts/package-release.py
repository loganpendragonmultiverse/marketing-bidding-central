#!/usr/bin/env python3
"""Package Git source only; exclude runtime files and dependency installations."""
import hashlib, pathlib, subprocess, zipfile
root=pathlib.Path(__file__).resolve().parents[1]
version=(root/'VERSION').read_text().strip()
files=subprocess.check_output(['git','ls-files','-z'],cwd=root).decode().split('\0')
out=root/'dist'; out.mkdir(exist_ok=True)
artifact=out/('marketing-bidding-central-'+version+'.zip')
prefix='marketing-bidding-central-'+version+'/'
with zipfile.ZipFile(artifact,'w',compression=zipfile.ZIP_DEFLATED,compresslevel=9) as z:
    for rel in sorted(filter(None,files)):
        if rel.startswith(('.git/','vendor/','dist/')) or (rel.startswith('.env') and rel!='.env.example') or rel=='.forge-task.json':
            raise RuntimeError('Unsafe tracked path: '+rel)
        p=root/rel
        info=zipfile.ZipInfo(prefix+rel,(2026,9,30,0,0,0))
        info.compress_type=zipfile.ZIP_DEFLATED
        info.external_attr=(0o100644)<<16
        z.writestr(info,p.read_bytes())
digest=hashlib.sha256(artifact.read_bytes()).hexdigest()
(out/'SHA256SUMS').write_text(digest+'  '+artifact.name+'\n')
print(artifact.name+' '+digest)
