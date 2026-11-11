#!/usr/bin/env python3
"""Ersatz für lftp, falls sich lftp auf dem Runner nicht installieren lässt: lädt ./public per FTPS auf den Webspace.
Umgebung: FTP_SERVER, FTP_USERNAME, FTP_PASSWORD, FTP_DIR. Optional FTP_PLAIN=1 (nur für lokale Tests)."""
import ftplib, os, re, ssl, sys

SRC = sys.argv[1] if len(sys.argv) > 1 else 'public'
EXCLUDE_FILES = {'config.php'}

class TLS(ftplib.FTP_TLS):
    """Datenverbindung nutzt die TLS-Sitzung der Steuerverbindung (viele Server verlangen das)."""
    def ntransfercmd(self, cmd, rest=None):
        conn, size = ftplib.FTP.ntransfercmd(self, cmd, rest)
        if self._prot_p:
            conn = self.context.wrap_socket(conn, server_hostname=self.host, session=self.sock.session)
        return conn, size

def main():
    host = re.sub(r'^[a-z]+://', '', os.environ['FTP_SERVER']).strip('/')
    port = 21
    if ':' in host: host, p = host.rsplit(':', 1); port = int(p)
    user, pw, base = os.environ['FTP_USERNAME'], os.environ['FTP_PASSWORD'], os.environ.get('FTP_DIR', '.') or '.'
    if os.environ.get('FTP_PLAIN'): ftp = ftplib.FTP(); ftp.connect(host, port, timeout=60); ftp.login(user, pw)
    else:
        ftp = TLS(context=ssl.create_default_context()); ftp.connect(host, port, timeout=60); ftp.login(user, pw); ftp.prot_p()
    ftp.set_pasv(True)
    ftp.cwd(base)
    made, sent = set(), 0
    def mkdirs(rel):
        parts = [x for x in rel.split('/') if x]; cur = ''
        for x in parts:
            cur = cur + '/' + x
            if cur in made: continue
            try: ftp.mkd(cur[1:])
            except ftplib.error_perm: pass  # existiert schon
            made.add(cur)
    for root, dirs, files in os.walk(SRC):
        dirs[:] = sorted(d for d in dirs if not d.startswith('.git'))
        rel = os.path.relpath(root, SRC); rel = '' if rel == '.' else rel.replace(os.sep, '/')
        mkdirs(rel)
        for f in sorted(files):
            if f.startswith('.git') or (rel == '' and f in EXCLUDE_FILES): continue
            target = (rel + '/' if rel else '') + f
            for attempt in (1, 2, 3):
                try:
                    with open(os.path.join(root, f), 'rb') as fh: ftp.storbinary('STOR ' + target, fh)
                    sent += 1; break
                except (ftplib.error_temp, OSError) as e:
                    if attempt == 3: raise
                    print('Wiederholung', target, e, flush=True)
    ftp.quit()
    print('Hochgeladen:', sent, 'Dateien')

main()
