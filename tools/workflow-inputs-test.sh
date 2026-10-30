#!/usr/bin/env bash

WORKFLOW_INPUT_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"

function _check_workflow_inputs() {
    python3 - "$WORKFLOW_INPUT_ROOT" "$1" <<'PY'
from contextlib import contextmanager
import os
from pathlib import Path
import re
import subprocess
import sys
import tempfile

root = Path(sys.argv[1])
mode = sys.argv[2]
cases = {
    'phel': ('.github/workflows/mutation-phel.yml', 'Run phel mutate', {'paths': 'src/phel/core', 'min-msi': '78'}, {'MIN_MSI': '78', 'MIN_COVERED_MSI': '84'}),
    'infection': ('.github/workflows/mutation.yml', 'Run Infection', {'min-msi': '80'}, {'MIN_MSI': '80', 'MIN_COVERED_MSI': '80'}),
    'coverage': ('.github/workflows/coverage.yml', 'Publish the figure and enforce the floor', {'coverage-floor': '85'}, {'COVERAGE_FLOOR': '85'}),
    'composer': ('.github/actions/setup-phel/action.yml', 'Install dependencies', {'composer-args': ''}, {}),
}
expression = re.compile(r'\$\{\{\s*(.*?)\s*\}\}')

def selected_step(file, name):
    lines = (root / file).read_text().splitlines()
    start = next(i for i, line in enumerate(lines) if line.strip() == '- name: ' + name)
    indent = len(lines[start]) - len(lines[start].lstrip())
    end = next((i for i in range(start + 1, len(lines)) if lines[i].startswith(' ' * indent + '- ')), len(lines))
    selected = lines[start:end]
    run = next(i for i, line in enumerate(selected) if line.strip().startswith('run:'))
    line = selected[run]
    run_indent = len(line) - len(line.lstrip())
    value = line.strip().removeprefix('run:').strip()
    script = '\n'.join(line[run_indent + 2:] for line in selected[run + 1:]) if value == '|' else value
    env = {}
    for line in selected[:run]:
        match = re.match(r'\s+(\w+): (\$\{\{.*\}\})$', line)
        if match:
            env[match[1]] = match[2]
    return script, env

def render(source, inputs, defaults):
    def value(match):
        parts = match[1].split(' || ')
        name = parts[0].removeprefix('github.event.').removeprefix('inputs.')
        provided = inputs.get(name, '')
        if provided or len(parts) == 1:
            return provided
        fallback = parts[1]
        return defaults[fallback[4:]] if fallback.startswith('env.') else fallback.strip("'")
    return expression.sub(value, source)

@contextmanager
def disposable_directory():
    with tempfile.TemporaryDirectory(prefix='phel-workflow-input-') as directory:
        try:
            yield directory
        finally:
            print('Removing workflow fixture: ' + directory)

def run_case(key, inputs):
    file, name, _, defaults = cases[key]
    script, step_env = selected_step(file, name)
    with disposable_directory() as directory:
        fixture = Path(directory)
        (fixture / 'bin').mkdir()
        stub = '#!/usr/bin/env bash\nprintf "%s\\0" "$@" > "$CAPTURE"\n'
        for command in ['phel', 'php', 'composer']:
            path = fixture / 'bin' / command
            path.write_text(stub)
            path.chmod(0o755)
        env = dict(os.environ, **defaults, CAPTURE=str(fixture / 'args'))
        env['PATH'] = str(fixture / 'bin') + os.pathsep + env['PATH']
        env.update({name: render(value, inputs, defaults) for name, value in step_env.items()})
        script = render(script, inputs, defaults)
        result = subprocess.run(['bash', '-e', '-o', 'pipefail', '-c', script], cwd=fixture, env=env, capture_output=True, text=True)
        assert not (fixture / 'injected').exists(), key + ': input executed shell code'
        assert result.returncode == 0, key + ': ' + result.stderr
        return [part.decode() for part in (fixture / 'args').read_bytes().split(b'\0')[:-1]]

def expected(key, inputs):
    values = dict(cases[key][2])
    values.update({name: value for name, value in inputs.items() if value})
    if key == 'phel':
        return ['mutate', values['paths'], '--parallel=auto', '--min-msi=' + values['min-msi'], '--min-covered-msi=84', '--reporter=json', '-o', 'data/mutation-phel.json']
    if key == 'infection':
        return ['-d', 'memory_limit=2G', 'vendor/bin/infection', '--threads=max', '--no-progress', '--no-interaction', '--skip-initial-tests', '--coverage=data/coverage-xml', '--min-msi=' + values['min-msi'], '--min-covered-msi=80']
    if key == 'coverage':
        return ['tools/check-coverage.php', 'data/clover.xml', values['coverage-floor']]
    return ['install', '--no-interaction', '--no-ansi', '--no-progress'] + values['composer-args'].split()

if mode == 'static':
    for file in (root / '.github').rglob('*'):
        if file.suffix not in ['.yml', '.yaml']:
            continue
        lines = file.read_text().splitlines()
        for index, line in enumerate(lines):
            match = re.match(r'^(\s*)run:\s*(.*)$', line)
            if not match:
                continue
            indent = len(match[1])
            block = [match[2]]
            for following in lines[index + 1:]:
                if following.strip() and len(following) - len(following.lstrip()) <= indent:
                    break
                block.append(following)
            assert not re.search(r'\$\{\{[^}]*\binputs\.', '\n'.join(block)), str(file) + ': inputs interpolated into run'
elif mode == 'defaults':
    for key in cases:
        for inputs in [{}, {name: '' for name in cases[key][2]}]:
            actual = run_case(key, inputs)
            assert actual == expected(key, inputs), (key, actual)
elif mode == 'ordinary':
    for key, inputs in [('phel', {'paths': 'src/phel/path with spaces', 'min-msi': '81'}), ('infection', {'min-msi': '82'}), ('coverage', {'coverage-floor': '86.5'}), ('composer', {'composer-args': '--prefer-dist\n--no-dev\t--optimize-autoloader'})]:
        actual = run_case(key, inputs)
        assert actual == expected(key, inputs), (key, actual)
else:
    key, field = mode.split(':')
    payload = 'x; printf injected > injected; #' if field == 'paths' else '$(printf injected > injected)`printf injected > injected`; printf injected > injected; #'
    inputs = {field: payload}
    actual = run_case(key, inputs)
    assert actual == expected(key, inputs), (key, actual)
print(mode + ': literal arguments preserved')
PY
}

function test_dispatch_path_is_one_literal_argument() {
    local output status=0
    output=$(_check_workflow_inputs phel:paths 2>&1) || status=$?
    assert_equals 0 "$status" "$output"
}

function test_infection_floor_is_literal() {
    local output status=0
    output=$(_check_workflow_inputs infection:min-msi 2>&1) || status=$?
    assert_equals 0 "$status" "$output"
}

function test_coverage_floor_is_literal() {
    local output status=0
    output=$(_check_workflow_inputs coverage:coverage-floor 2>&1) || status=$?
    assert_equals 0 "$status" "$output"
}

function test_composer_arguments_do_not_execute_shell_syntax() {
    local output status=0
    output=$(_check_workflow_inputs composer:composer-args 2>&1) || status=$?
    assert_equals 0 "$status" "$output"
}

function test_phel_mutation_floor_is_literal() {
    local output status=0
    output=$(_check_workflow_inputs phel:min-msi 2>&1) || status=$?
    assert_equals 0 "$status" "$output"
}

function test_dispatch_and_scheduled_defaults_are_preserved() {
    local output status=0
    output=$(_check_workflow_inputs defaults 2>&1) || status=$?
    assert_equals 0 "$status" "$output"
}

function test_ordinary_arguments_and_paths_with_spaces_are_preserved() {
    local output status=0
    output=$(_check_workflow_inputs ordinary 2>&1) || status=$?
    assert_equals 0 "$status" "$output"
}

function test_no_action_or_workflow_interpolates_inputs_into_run() {
    local output status=0
    output=$(_check_workflow_inputs static 2>&1) || status=$?
    assert_equals 0 "$status" "$output"
}
