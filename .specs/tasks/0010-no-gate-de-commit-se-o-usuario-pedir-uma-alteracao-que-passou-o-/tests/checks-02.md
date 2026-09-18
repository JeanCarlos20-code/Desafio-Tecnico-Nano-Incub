# Deterministic checks — round 02

- ✅ `cd harness && python3 -m pytest tests -q --ignore=tests/test_graph_e2e.py` — exit=0 (required)
- ✅ `cd harness && python3 -m pytest tests/test_graph_e2e.py -q` — exit=0 (required)
- ✅ `cd harness && python3 -m compileall -q src` — exit=0 (required)
- ✅ `php artisan test && npm run test` — exit=0 (required)
- ✅ `vendor/bin/pint --test && npm run lint` — exit=0 (required)
- ✅ `composer run build && npm run build` — exit=0 (required)
- ✅ `python3 -m pytest tests -q` — exit=0 (required)
- ✅ `python3 -m compileall -q src` — exit=0 (required)
- ✅ `PYTHONPATH=src python3 -c "import project_harness"` — exit=0 (required)
