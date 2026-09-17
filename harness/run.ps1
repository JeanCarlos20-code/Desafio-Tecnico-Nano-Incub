$Here = Split-Path -Parent $MyInvocation.MyCommand.Path
$env:PYTHONPATH = "$Here/src" + $(if ($env:PYTHONPATH) { ";$env:PYTHONPATH" } else { "" })
python -m project_harness.cli @args
exit $LASTEXITCODE
