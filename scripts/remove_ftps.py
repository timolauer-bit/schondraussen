#!/usr/bin/env python3
"""Löscht nach dem Upload die in deploy-remove.txt genannten Dateien und Ordner (rekursiv) auf dem Webspace.
Umgebung: FTP_SERVER, FTP_USERNAME, FTP_PASSWORD, FTP_DIR. Nur Pfade innerhalb des Webspace-Ordners, ohne '..'."""
import ftplib, os, re, ssl, sys

class TLS(ftplib.FTP_TLS):
    def ntransfercmd(self, cmd, rest=None):
        conn, size = ftplib.FTP.ntransfercmd(self, cmd, rest)
        if self._prot_p:
            conn = self.context.wrap_socket(conn, server_hostname=self.host, session=self.sock.session)
        return conn, size

def connect():
    host = re.sub(r'^[a-z]+://', '', os.environ['FTP_SERVER']).strip('/'); port = 21
    if ':' in host: host, p = host.rsplit(':', 1); port = int(p)
    ftp = TLS(context=ssl.create_default_context()); ftp.connect(host, port, timeout=60)
    ftp.login(os.environ['FTP_USERNAME'], os.environ['FTP_PASSWORD']); ftp.prot_p(); ftp.set_pasv(True); ftp.cwd(os.environ.get('FTP_DIR', '.') or '.')
    return ftp

def remove(ftp, path):
    try: ftp.delete(path); print('gelöscht:', path); return
    except ftplib.error_perm: pass
    try: names = ftp.nlst(path)
    except ftplib.error_perm: print('nicht vorhanden:', path); return
    for n in names:
        b = n.rsplit('/', 1)[-1]
        if b in ('.', '..') or n.rstrip('/') == path.rstrip('/'): continue
        remove(ftp, n if '/' in n else path + '/' + n)
    try: ftp.rmd(path); print('Ordner gelöscht:', path)
    except ftplib.error_perm as e: print('Ordner nicht gelöscht:', path, e)

paths = []
if os.path.isfile('deploy-remove.txt'):
    for line in open('deploy-remove.txt', encoding='utf-8'):
        p = line.strip().strip('/')
        if not p or p.startswith('#'): continue
        if '..' in p.split('/') or p in ('config.php', 'lib', 'api', 'dev', 'designs'): sys.exit('Pfad nicht erlaubt: ' + p)
        paths.append(p)
if not paths: print('Nichts zu löschen'); sys.exit(0)
ftp = connect()
for p in paths: remove(ftp, p)
ftp.quit()
