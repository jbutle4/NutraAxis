# More info about Python and (mod_)WSGI:
# https://wsgi.readthedocs.io
# https://modwsgi.readthedocs.io

import mysql.connector
import sys

def application(environ, start_response):
    spaces = '        '
    lf = '\n'
    html = pageBegin()

    html += spaces + '<p class="header">The Python application returned:</p>' + lf
    html += spaces + '<ul>' + lf
    html += spaces + '<li>sys.prefix: ' + sys.prefix + '</li>' + lf
    html += spaces + '<li>SERVER_NAME: ' + environ['SERVER_NAME'] + '</li>' + lf
    html += spaces + '<li>SERVER_PORT: ' + environ['SERVER_PORT'] + '</li>' + lf
    html += spaces + '<li>SERVER_PROTOCOL: ' + environ['SERVER_PROTOCOL'] + '</li>' + lf
    html += spaces + '<li>SERVER_ADDR: ' + environ['SERVER_ADDR'] + '</li>' + lf
    html += spaces + '<li>SCRIPT_NAME: ' + environ['SCRIPT_NAME'] + '</li>' + lf
    html += spaces + '<li>PATH_INFO: ' + environ['PATH_INFO'] + '</li>' + lf
    html += spaces + '<li>DOCUMENT_ROOT: ' + environ['DOCUMENT_ROOT'] + '</li>' + lf
    html += spaces + '<li>HTTP_HOST: ' + environ['HTTP_HOST'] + '</li>' + lf
    html += spaces + '<li>HTTP_USER_AGENT: ' + environ['HTTP_USER_AGENT'] + '</li>' + lf
    html += spaces + '<li>QUERY_STRING: ' + environ['QUERY_STRING'] + '</li>' + lf
    html += spaces + '<li>REQUEST_URI: ' + environ['REQUEST_URI'] + '</li>' + lf
    html += spaces + '<li>SCRIPT_FILENAME: ' + environ['SCRIPT_FILENAME'] + '</li></br>' + lf

    version = environ['mod_wsgi.version']
    html += spaces + '<li>mod_wsgi.version: ' + f'{version[0]}.{version[1]}.{version[2]}' + '</li>' + lf

    html += spaces + '<li>mod_wsgi.listener_host: ' + environ['mod_wsgi.listener_host'] + '</li>' + lf
    html += spaces + '<li>mod_wsgi.listener_port: ' + environ['mod_wsgi.listener_port'] + '</li>' + lf
    html += spaces + '<li>mod_wsgi.process_group: ' + environ['mod_wsgi.process_group'] + '</li>' + lf
    html += spaces + '<li>mod_wsgi.request_handler: ' + environ['mod_wsgi.request_handler'] + '</li>' + lf
    html += spaces + '<li>wsgi.multiprocess: ' + str(environ['wsgi.multiprocess']) + '</li>' + lf
    html += spaces + '<li>wsgi.multithread: ' + str(environ['wsgi.multithread']) + '</li>' + lf
    html += spaces + '</ul></br>' + lf

    # Let's see if we can chat with the database server
    config = {
        'user': 'root',
        'password': 'root',
        'unix_socket': '/Applications/MAMP/tmp/mysql/mysql.sock',
        'raise_on_warnings': True
    }

    cnx = mysql.connector.connect(**config)
    if (cnx.is_connected()): # Yes, we can.
        cursor = cnx.cursor(dictionary = True)
        
        cursor.execute('SHOW VARIABLES LIKE \'version\'')
        dbresults = cursor.fetchall()
        mysqlHeader = 'MySQL ' + dbresults[0]['Value'] + ':'

        html += spaces + '<p class="header">' + mysqlHeader + '</p>' + lf

        cursor.execute('SELECT USER()')
        dbresults = cursor.fetchall()

        html += spaces + '<ul>' + lf
        html += spaces + '<li>User: ' + dbresults[0]['USER()'] + '</li>' + lf

        cursor.execute('SELECT `SCHEMA_NAME` FROM `INFORMATION_SCHEMA`.`SCHEMATA`')
        dbresults = cursor.fetchall()
        cnx.close()

        html += spaces + '<li>Databases: ' + str(len(dbresults)) + '</li>' + lf

        for oneDb in dbresults:
            html += spaces + '<li>➔ ' + oneDb['SCHEMA_NAME'] + '</li>' + lf

        html += spaces + '</ul>' + lf

    html += pageEnd()
    output = html.encode('utf-8') # html is a string object, but we MUST return a bytes object (i.e. b'Hello World')

    status = '200 OK'
    response_headers = [('Content-type', 'text/html'),
                        ('Content-Length', str(len(output)))]
    start_response(status, response_headers)

    return [output]


def pageBegin():
    return '''<!DOCTYPE html>
<html>
    <head>
        <meta charset="UTF-8">
        <title>Hello MAMP PRO!</title>
        <style>
            body {
                font-family: monospace;
                line-height: 80%;
                color: #000000;
                background-color: #FFFFFF;
                margin: 0;
                padding: 28px 20px 20px 120px;
                background-image: url('data:image/svg+xml;utf8,<svg width="100%" height="100%" viewBox="0 0 2048 2048" version="1.1" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" xml:space="preserve" xmlns:serif="http://www.serif.com/" style="fill-rule:evenodd;clip-rule:evenodd;stroke-linejoin:round;stroke-miterlimit:1.41421;"><circle cx="1035.06" cy="1039.06" r="814.588" style="fill:rgb(37,124,175);"/><g transform="matrix(1,0,0,1,915.735,621.522)"></g><g transform="matrix(39.9757,0,0,39.9757,435.174,509.385)"><path d="M0.63,10.25C0.94,8.105 3.335,1.421 10.06,0.153C14.028,-0.595 18.922,1.462 22.358,4.946C20.645,6.122 19.499,7.138 18.634,8.372C19.279,10.767 21.185,11.742 22.528,12.147C21.041,11.249 20.102,10.4 19.521,8.591C22.701,4.81 26.106,3.587 26.394,3.558C29.053,5.679 30.93,9.548 30.863,13.622C30.784,18.339 28.219,23.2 25.278,25.162C24.874,25.355 23.945,24.846 23.818,24.294C24.42,22.896 25.844,19.491 26.019,16.948C26.185,14.507 24.569,13.074 23.213,14.838C21.328,17.993 20.506,21.913 19.683,26.142C18.298,26.538 15.948,26.638 14.932,26.096C14.803,23.479 15.413,19.009 13.677,17.442C12.933,16.769 12.199,17.081 11.701,17.701C10.446,19.255 10.471,23.286 10.714,25.899C9.31,26.766 6.931,26.585 5.427,26.047C4.824,21.94 3.407,16.935 2.248,13.103C1.925,14.643 1.769,16.474 1.819,18.048C1.045,17.418 0.164,14.105 0.63,10.25Z" style="fill:white;"/></g></svg>');
                background-repeat: no-repeat;
                background-size: 80px 80px;
                background-position: 17px 5px;
            }
            .header {
                font-family: Arial, Helvetica, sans-serif;
                font-size: .9em;
                font-weight: bold;
            }
            .header:not(:first-child) {
                margin-top: 20px;
            }
            ul {
                list-style: none;
                padding-left: 0;
                line-height: 140%;
            }
        </style>
    </head>
    <body>
'''


def pageEnd():
    return '''    </body>
</html>
'''
