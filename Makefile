PEST := ./vendor/bin/pest
PINT := ./vendor/bin/pint
COMPOSER := composer

ifneq (,$(wildcard .env))
	include .env
	export
endif

.PHONY: help test unit integration smoke smoke-mysql smoke-pgsql smoke-sqlsrv pint format analyse validate audit ci

help:
	@printf "Available targets:\n"
	@printf "  make test          Run the full default test suite\n"
	@printf "  make unit          Run unit tests only\n"
	@printf "  make integration   Run integration tests; unconfigured databases are skipped\n"
	@printf "  make smoke         Run all configured database smoke tests\n"
	@printf "  make smoke-mysql   Run MySQL smoke test only\n"
	@printf "  make smoke-pgsql   Run PostgreSQL smoke test only\n"
	@printf "  make smoke-sqlsrv  Run SQL Server smoke test only\n"
	@printf "  make pint          Check formatting without changing files\n"
	@printf "  make format        Apply Laravel Pint formatting\n"
	@printf "  make analyse       Run PHPStan Level 8 analysis\n"
	@printf "  make validate      Validate Composer configuration\n"
	@printf "  make audit         Audit Composer dependencies\n"
	@printf "  make ci            Run all read-only quality checks\n"
	@printf "\n"
	@printf "Copy .env.example to .env to configure database smoke tests.\n"

test:
	$(PEST)

unit:
	$(PEST) tests/Unit

integration smoke:
	$(PEST) tests/Integration

smoke-mysql:
	KNOB_SMOKE_ONLY=mysql $(PEST) tests/Integration

smoke-pgsql:
	KNOB_SMOKE_ONLY=pgsql $(PEST) tests/Integration

smoke-sqlsrv:
	KNOB_SMOKE_ONLY=sqlsrv $(PEST) tests/Integration

pint:
	$(COMPOSER) lint

format:
	$(PINT)

analyse:
	$(COMPOSER) analyse

validate:
	$(COMPOSER) validate --strict

audit:
	$(COMPOSER) audit

ci:
	$(COMPOSER) ci
