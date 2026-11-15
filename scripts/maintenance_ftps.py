#!/usr/bin/env python3
"""Wartungsmodus für Deployments: 'on' lädt zuerst die Hilfsdatei lib/maintenance.php hoch und legt dann maintenance.flag an,
'off' löscht die Datei wieder. Umgebung: FTP_SERVER, FTP_USERNAME, FTP_PASSWORD, FTP_DIR. Die Seite blendet sich nach 10 Minuten von selbst aus."""
import ftplib, os, re, ssl, sys, io, time

class TLS(ftplib.FTP_TLS):
    def ntransfercmd(self, cmd, rest=None):
        conn, size = ftplib.FTP.ntransfercmd(self, cmd, rest)
        if self._prot_p:
            conn = self.context.wrap_socket(conn, server_hostname=self.host, session=self.sock.session)
        return conn, size

def connect():
    host = re.sub(r'^[a-z]+://', '', os.environ['FTP_SERVER']).strip('/'); port = 21
    if ':' in host: host, p = host.rsplit(':', 1); port = int(p)
    user, pw, base = os.environ['FTP_USERNAME'], os.environ['FTP_PASSWORD'], os.environ.get('FTP_DIR', '.') or '.'
    ftp = TLS(context=ssl.create_default_context()); ftp.connect(host, port, timeout=60); ftp.login(user, pw); ftp.prot_p(); ftp.set_pasv(True); ftp.cwd(base)
    return ftp

mode = sys.argv[1] if len(sys.argv) > 1 else ''
ftp = connect()
if mode == 'on':
    with open('public/lib/maintenance.php', 'rb') as fh: ftp.storbinary('STOR lib/maintenance.php', fh)
    ftp.storbinary('STOR maintenance.flag', io.BytesIO(('Wartung seit ' + time.strftime('%Y-%m-%d %H:%M:%S') + '\n').encode()))
    print('Wartungsmodus eingeschaltet')
elif mode == 'off':
    try: ftp.delete('maintenance.flag'); print('Wartungsmodus ausgeschaltet')
    except ftplib.error_perm: print('Keine Wartungsdatei vorhanden')
else:
    sys.exit('Aufruf: maintenance_ftps.py on|off')
ftp.quit()
