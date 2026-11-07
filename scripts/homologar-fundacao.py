#!/usr/bin/env python3
"""Homologação local: serviços descartáveis, sem usar bancos/credenciais existentes."""
import argparse
import base64
import html
import http.cookiejar
import json
import os
from pathlib import Path
import re
import secrets
import shutil
import socket
import subprocess
import tempfile
import time
import urllib.error
import urllib.parse
import urllib.request

ROOT = Path(__file__).resolve().parent.parent
API = ROOT / 'apps/api'
WEB = ROOT / 'apps/web'
PORTS = {'mysql': 33307, 'redis': 26380, 'smtp': 11025, 'mailpit': 18025, 'api': 18000}


class Session:
    def __init__(self, base):
        self.base = base
        self.jar = http.cookiejar.CookieJar()
        self.client = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(self.jar))

    def request(self, method, path, data=None):
        headers = {'Accept': 'application/json'}
        if method == 'POST':
            headers['Content-Type'] = 'application/json'
            headers['X-XSRF-TOKEN'] = next((urllib.parse.unquote(c.value) for c in self.jar if c.name == 'XSRF-TOKEN'), '')
        request = urllib.request.Request(self.base + path, data=None if data is None else json.dumps(data).encode(), headers=headers, method=method)
        try:
            response = self.client.open(request, timeout=20)
        except urllib.error.HTTPError as error:
            response = error
        raw = response.read()
        return response.status, json.loads(raw) if raw else None


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--php', action='append', required=True, help='Binário PHP; repetir para matriz de versões')
    parser.add_argument('--mysql-bin', type=Path)
    parser.add_argument('--redis-server')
    parser.add_argument('--mailpit')
    parser.add_argument('--compose', action='store_true', help='Usar infra/compose.yaml com projeto/volumes descartáveis')
    parser.add_argument('--docker', default='docker')
    parser.add_argument('--docker-host', help='Socket unix local obrigatório no modo Compose')
    parser.add_argument('--docker-config', help='Configuração Docker isolada opcional')
    parser.add_argument('--mysql-library-path', help='DYLD_LIBRARY_PATH apenas dos processos MySQL deste roteiro')
    parser.add_argument('--result', type=Path, default=Path('/private/tmp/factorycontrol-homologation.json'))
    args = parser.parse_args()
    if args.compose and not (args.docker_host or '').startswith('unix://'):
        parser.error('--compose exige --docker-host unix://... local explícito.')
    if not args.compose and not all([args.mysql_bin, args.redis_server, args.mailpit]):
        parser.error('Modo nativo exige --mysql-bin, --redis-server e --mailpit.')
    versions = {}
    compose = None
    docker_env = {**os.environ, 'DOCKER_HOST': args.docker_host or ''}
    # DOCKER_CONTEXT herdado pode sobrepor o socket local escolhido.
    docker_env.pop('DOCKER_CONTEXT', None)
    if args.docker_config:
        docker_env['DOCKER_CONFIG'] = args.docker_config
    events = []
    processes = []
    handles = []
    private = []
    def redact(value):
        for secret in private:
            value = value.replace(secret, '[REDACTED]')
        return value
    def run(label, command, env=None, cwd=API, input_text=None, accepted=(0,)):
        print(label, flush=True)
        result = subprocess.run(command, cwd=cwd, env=env, input=input_text, capture_output=True, text=True)
        output = redact(result.stdout + result.stderr)
        events.append({'check': label, 'exit': result.returncode, 'output': output[-12000:]})
        print('\n'.join(output.splitlines()[-6:]), flush=True)
        if result.returncode not in accepted:
            raise RuntimeError(label + ' falhou: ' + output[-7000:])
        return output
    def launch(label, command, env, cwd, temp):
        log = (temp / (label + '.log')).open('w+')
        handles.append(log)
        process = subprocess.Popen(command, env=env, cwd=cwd, stdout=log, stderr=log)
        processes.append((process, log))
        return process
    def wait_port(port, process, timeout=45):
        deadline = time.monotonic() + timeout
        while time.monotonic() < deadline:
            if process is not None and process.poll() is not None:
                raise RuntimeError('Serviço encerrou na porta ' + str(port))
            try:
                with socket.create_connection(('127.0.0.1', port), timeout=.2):
                    return
            except OSError:
                time.sleep(.2)
        raise RuntimeError('Timeout na porta ' + str(port))
    def stop(process):
        if process.poll() is None:
            process.terminate()
            try:
                process.wait(timeout=15)
            except subprocess.TimeoutExpired:
                process.kill()
                process.wait()
    def assert_status(actual, expected, label):
        if actual != expected:
            raise RuntimeError(label + ': status ' + str(actual) + ', esperado ' + str(expected))

    for port in [*PORTS.values(), 8000, 5173]:
        with socket.socket() as check:
            try:
                check.bind(('127.0.0.1', port))
            except OSError:
                raise RuntimeError('Porta ocupada; nenhum processo será encerrado: ' + str(port))
    mysql_env = os.environ.copy()
    if args.mysql_library_path:
        mysql_env['DYLD_LIBRARY_PATH'] = args.mysql_library_path
    if not args.compose:
        mysql_version = run('Versão MySQL', [str(args.mysql_bin / 'mysqld'), '--no-defaults', '--version'], mysql_env)
        if not re.search(r'Ver 8\.4\.', mysql_version):
            raise RuntimeError('É obrigatório usar MySQL 8.4.')
        versions['mysql'] = mysql_version.strip()
        versions['redis'] = run('Versão Redis', [args.redis_server, '--version']).strip()
        versions['mailpit'] = run('Versão Mailpit', [args.mailpit, 'version', '--no-release-check']).strip()
    for php in args.php:
        versions[php] = run('Versão PHP', [php, '-v']).splitlines()[0]
    if not any('PHP 8.3.' in version for version in versions.values()):
        raise RuntimeError('Inclua o PHP 8.3 na matriz.')

    succeeded = False
    with tempfile.TemporaryDirectory(prefix='fc0-', dir='/private/tmp') as directory:
        temp = Path(directory)
        password = secrets.token_hex(24)
        demo = 'Ficticia-9!' + secrets.token_hex(16)
        private.extend([password, demo])
        data = temp / 'mysql'
        try:
            if args.compose:
                project = 'factorycontrol-phase0-' + secrets.token_hex(5)
                env_file = temp / 'compose.env'
                compose_values = {
                    'MYSQL_DATABASE': 'factorycontrol_pro_test',
                    'MYSQL_USER': 'factorycontrol',
                    'MYSQL_PASSWORD': secrets.token_hex(24),
                    'MYSQL_ROOT_PASSWORD': password,
                    'MYSQL_PORT': str(PORTS['mysql']),
                    'REDIS_PORT': str(PORTS['redis']),
                    'MAILPIT_WEB_PORT': str(PORTS['mailpit']),
                    'MAILPIT_SMTP_PORT': str(PORTS['smtp']),
                }
                private.append(compose_values['MYSQL_PASSWORD'])
                docker_env.update(compose_values)
                env_file.write_text('\n'.join(key + '=' + value for key, value in compose_values.items()) + '\n')
                env_file.chmod(0o600)
                compose = [args.docker, 'compose', '--project-name', project,
                           '--file', str(ROOT / 'infra/compose.yaml'), '--env-file', str(env_file)]
                run('Validar Compose original', compose + ['config', '--quiet'], docker_env)
                run('Subir Compose isolado', compose + ['up', '--detach', '--wait', '--wait-timeout', '180', '--quiet-pull'], docker_env)
                for service in ['mysql', 'redis', 'mailpit']:
                    wait_port(PORTS[service], None)
                run('Estado dos serviços Compose', compose + ['ps', '--format', 'json'], docker_env)
                versions['docker'] = run('Versão Docker', [args.docker, '--version'], docker_env).strip()
                versions['compose'] = run('Versão Compose', [args.docker, 'compose', 'version'], docker_env).strip()
                versions['mysql'] = run('MySQL container', compose + ['exec', '-T', 'mysql', 'mysqld', '--version'], docker_env).strip()
                versions['redis'] = run('Redis container', compose + ['exec', '-T', 'redis', 'redis-server', '--version'], docker_env).strip()
                versions['mailpit'] = run('Mailpit container', compose + ['exec', '-T', 'mailpit', '/mailpit', 'version', '--no-release-check'], docker_env).strip()
                if not re.search(r'Ver 8\.4\.', versions['mysql']) or not re.search(r'v=7\.', versions['redis']):
                    raise RuntimeError('Compose deve executar MySQL 8.4 e Redis 7.')
                run('Imagens Compose', compose + ['images', '--format', 'json'], docker_env)
                run('Criar bancos de teste no projeto exclusivo', compose + ['exec', '-T', 'mysql', 'sh', '-c',
                    'MYSQL_PWD="$MYSQL_ROOT_PASSWORD" exec mysql -uroot --default-character-set=utf8mb4'], docker_env,
                    input_text='CREATE DATABASE IF NOT EXISTS factorycontrol_pro_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci; CREATE DATABASE factorycontrol_pro_e2e_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;')
            else:
                run('Inicializar MySQL DESCARTÁVEL', [str(args.mysql_bin / 'mysqld'), '--no-defaults', '--initialize-insecure', '--basedir=' + str(args.mysql_bin.parent), '--datadir=' + str(data)], mysql_env)
                mysql = launch('mysql', [str(args.mysql_bin / 'mysqld'), '--no-defaults', '--basedir=' + str(args.mysql_bin.parent), '--datadir=' + str(data), '--socket=' + str(temp / 'mysql.sock'), '--port=' + str(PORTS['mysql']), '--bind-address=127.0.0.1', '--mysqlx=0', '--pid-file=' + str(temp / 'mysql.pid'), '--default-time-zone=+00:00', '--character-set-server=utf8mb4', '--collation-server=utf8mb4_unicode_ci'], mysql_env, temp, temp)
                wait_port(PORTS['mysql'], mysql)
                run('Criar bancos locais e senha efêmera', [str(args.mysql_bin / 'mysql'), '--no-defaults', '-u', 'root', '--protocol=socket', '--socket=' + str(temp / 'mysql.sock')], mysql_env, input_text="ALTER USER 'root'@'localhost' IDENTIFIED BY '" + password + "'; CREATE DATABASE factorycontrol_pro_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci; CREATE DATABASE factorycontrol_pro_e2e_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;")
                redis = launch('redis', [args.redis_server, '--bind', '127.0.0.1', '--port', str(PORTS['redis']), '--dir', str(temp), '--save', '', '--appendonly', 'no'], os.environ.copy(), temp, temp)
                wait_port(PORTS['redis'], redis)
                mail_env = {key: value for key, value in os.environ.items() if not key.startswith('MP_')}
                mail = launch('mailpit', [args.mailpit, '--listen', '127.0.0.1:' + str(PORTS['mailpit']), '--smtp', '127.0.0.1:' + str(PORTS['smtp']), '--database', str(temp / 'mail.db'), '--disable-version-check', '--smtp-disable-rdns', '--smtp-allowed-recipients', '@example[.]test$', '--quiet'], mail_env, temp, temp)
                wait_port(PORTS['mailpit'], mail)
            env = {**os.environ, 'APP_ENV': 'local', 'APP_DEBUG': 'false', 'APP_KEY': 'base64:' + base64.b64encode(secrets.token_bytes(32)).decode(), 'DB_URL': '', 'DB_CONNECTION': 'mysql', 'DB_HOST': '127.0.0.1', 'DB_PORT': str(PORTS['mysql']), 'DB_SOCKET': '', 'DB_DATABASE': 'factorycontrol_pro_test', 'DB_USERNAME': 'root', 'DB_PASSWORD': password, 'REDIS_CLIENT': 'predis', 'REDIS_HOST': '127.0.0.1', 'REDIS_PORT': str(PORTS['redis']), 'REDIS_URL': '', 'REDIS_PASSWORD': 'null', 'REDIS_PREFIX': 'fc0-' + secrets.token_hex(8) + '-', 'CACHE_PREFIX': 'foundation-', 'CACHE_STORE': 'redis', 'CACHE_LIMITER': 'file', 'CACHE_FILE_PATH': str(temp / 'cache'), 'SESSION_DRIVER': 'database', 'SESSION_SECURE_COOKIE': 'false', 'SESSION_DOMAIN': 'null', 'QUEUE_CONNECTION': 'redis', 'MAIL_MAILER': 'smtp', 'MAIL_SCHEME': 'smtp', 'MAIL_URL': '', 'MAIL_HOST': '127.0.0.1', 'MAIL_PORT': str(PORTS['smtp']), 'MAIL_USERNAME': 'null', 'MAIL_PASSWORD': 'null', 'MAIL_FROM_ADDRESS': 'nao-responda@example.test', 'DEMO_PASSWORD': demo, 'APP_URL': 'http://127.0.0.1:' + str(PORTS['api']), 'FRONTEND_URL': 'http://localhost:5173', 'APP_LOCALE': 'pt_BR', 'APP_CONFIG_CACHE': str(temp / 'nonexistent-config.php')}
            for php in args.php:
                current = {**env, 'PATH': str(Path(php).parent) + os.pathsep + os.environ['PATH']}
                for action in ['migrate', 'migrate:rollback', 'migrate']:
                    run(Path(php).parent.parent.name + ' MySQL ' + action, [php, 'artisan', action, '--force'], current)
                testing = {**current, 'APP_ENV': 'testing', 'CACHE_STORE': 'array', 'CACHE_LIMITER': 'array', 'SESSION_DRIVER': 'array', 'MAIL_MAILER': 'array', 'QUEUE_CONNECTION': 'sync', 'BCRYPT_ROUNDS': '4', 'TEST_DB_CONNECTION': 'mysql', 'TEST_DB_DATABASE': 'factorycontrol_pro_test'}
                run('Suíte real MySQL/Redis ' + php, [php, 'artisan', 'test'], testing)
                run('Dependências runtime ' + php, [php, '/usr/local/bin/composer', 'check-platform-reqs'], current)
            php = next(binary for binary in args.php if versions[binary].startswith('PHP 8.3.'))
            current = {**env, 'PATH': str(Path(php).parent) + os.pathsep + os.environ['PATH']}
            run('Seed fictício para SMTP', [php, 'artisan', 'db:seed', '--class=DemoSeeder', '--force'], current)
            server = launch('api', [php, '-S', '127.0.0.1:' + str(PORTS['api']), '-t', str(API / 'public'), str(API / 'vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php')], current, API / 'public', temp)
            wait_port(PORTS['api'], server)
            base = env['APP_URL']
            a, b = Session(base), Session(base)
            status, health = a.request('GET', '/api/v1/health')
            assert_status(status, 200, 'Readiness real')
            assert health['data']['checks'] == {'database': True, 'redis': True}
            a.request('GET', '/sanctum/csrf-cookie')
            assert_status(a.request('POST', '/api/v1/auth/login', {'email': 'demo@example.test', 'password': demo})[0], 200, 'Login real')
            assert_status(a.request('GET', '/api/v1/me')[0], 200, 'Sessão real')
            b.request('GET', '/sanctum/csrf-cookie')
            assert_status(b.request('POST', '/api/v1/auth/forgot-password', {'email': 'demo@example.test'})[0], 200, 'Envio SMTP')
            mail_api = Session('http://127.0.0.1:' + str(PORTS['mailpit']))
            messages = mail_api.request('GET', '/api/v1/messages')[1]['messages']
            assert len(messages) == 1, 'Esperado exatamente um e-mail fictício.'
            message = mail_api.request('GET', '/api/v1/message/' + messages[0]['ID'])[1]
            content = html.unescape(message.get('Text', '') + '\n' + message.get('HTML', ''))
            match = re.search(r'http://localhost:5173/redefinir-senha\?[^\s<>"\)]+', content)
            assert match, 'Link de recuperação não encontrado.'
            query = urllib.parse.parse_qs(urllib.parse.urlparse(match.group(0)).query)
            token = query['token'][0]
            private.append(token)
            new_password = 'Nova-Ficticia9!' + secrets.token_hex(12)
            private.append(new_password)
            payload = {'email': 'demo@example.test', 'token': token, 'password': new_password, 'password_confirmation': new_password}
            assert_status(b.request('POST', '/api/v1/auth/reset-password', payload)[0], 200, 'Reset via e-mail Mailpit')
            assert_status(a.request('GET', '/api/v1/me')[0], 401, 'Sessão anterior revogada')
            assert_status(b.request('POST', '/api/v1/auth/reset-password', payload)[0], 422, 'Token de uso único')
            assert_status(a.request('POST', '/api/v1/auth/login', {'email': 'demo@example.test', 'password': demo})[0], 422, 'Senha anterior rejeitada')
            assert_status(a.request('POST', '/api/v1/auth/login', {'email': 'demo@example.test', 'password': new_password})[0], 200, 'Nova senha aceita')
            assert_status(a.request('POST', '/api/v1/auth/logout', {})[0], 200, 'Logout real')
            events.append({'check': 'SMTP Mailpit + reset + revogação + token único + senha anterior/nova + logout', 'exit': 0})
            print('SMTP/RESET/SESSÃO REAIS: passou (somente destinatário fictício local).', flush=True)
            if args.compose:
                run('Desligar Redis 7 real', compose + ['stop', 'redis'], docker_env)
            else:
                stop(redis)
            assert_status(Session(base).request('GET', '/api/v1/health')[0], 503, 'Readiness sem Redis')
            events.append({'check': 'Readiness real com Redis desligado retorna 503', 'exit': 0})
            stop(server)
            if args.compose:
                run('Religar Redis 7 real', compose + ['start', 'redis'], docker_env)
                wait_port(PORTS['redis'], None)
            else:
                redis = launch('redis-restarted', [args.redis_server, '--bind', '127.0.0.1', '--port', str(PORTS['redis']), '--dir', str(temp), '--save', '', '--appendonly', 'no'], os.environ.copy(), temp, temp)
                wait_port(PORTS['redis'], redis)
            profile = temp / 'e2e-profile.json'
            profile.write_text(json.dumps({**env, 'DB_DATABASE': 'factorycontrol_pro_e2e_test'}))
            profile.chmod(0o600)
            e2e_env = {**current, 'FACTORYCONTROL_E2E_PROFILE': str(profile)}
            run('Web lint', ['npm', 'run', 'lint'], current, WEB)
            run('Web typecheck', ['npm', 'run', 'typecheck'], current, WEB)
            run('Web unit', ['npm', 'test'], current, WEB)
            run('Web build', ['npm', 'run', 'build'], current, WEB)
            run('E2E PHP 8.3 + MySQL 8.4 + Redis reais', ['npm', 'run', 'test:e2e'], e2e_env, WEB)
            run('Pint PHP 8.3', [php, 'vendor/laravel/pint/builds/pint', '--test'], current)
            run('Composer validate PHP 8.3', [php, '/usr/local/bin/composer', 'validate', '--strict'], current)
            succeeded = True
        finally:
            for process, log in reversed(processes):
                stop(process)
                if not succeeded:
                    log.seek(0)
                    print(redact(log.read()[-3500:]), flush=True)
            for handle in handles:
                handle.close()
            cleanup_ok = True
            if compose is not None:
                cleanup = subprocess.run(compose + ['down', '--volumes', '--remove-orphans'], env=docker_env, capture_output=True, text=True)
                cleanup_ok = cleanup.returncode == 0
                events.append({'check': 'Remover somente projeto/volumes descartáveis Compose', 'exit': cleanup.returncode, 'output': redact(cleanup.stdout + cleanup.stderr)})
                print('Limpeza Compose: ' + str(cleanup.returncode), flush=True)
            args.result.write_text(json.dumps({'success': succeeded and cleanup_ok, 'versions': versions, 'checks': events, 'ports': PORTS, 'temporary_services_stopped': cleanup_ok, 'service_mode': 'compose' if args.compose else 'native'}, indent=2, ensure_ascii=False) + '\n')
            args.result.chmod(0o600)
            if not cleanup_ok:
                raise RuntimeError('A remoção dos recursos descartáveis Compose falhou; consulte a evidência.')
    print('Homologação concluída; serviços/bancos temporários encerrados e removidos. Evidência: ' + str(args.result), flush=True)


if __name__ == '__main__':
    main()
