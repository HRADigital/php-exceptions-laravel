.PHONY: help syntax lint lint-scope-check lint-fix cs analyse validate validate-implementation test

SHELL := /bin/bash

# This package is a plain Composer library - there is no Docker container, no npm
# asset pipeline, no queues and no deploy step, so none of those target families
# exist here. EXEC is kept as the single prefix every PHP tool goes through so the
# same targets can be pointed at a container later (`make lint EXEC="docker exec x"`)
# without editing a recipe. Empty by default: everything runs natively.
EXEC :=

# Per-run path ($$ = PID of the shell make spawns). A fixed path would let parallel
# `make lint` invocations clobber each other's scope report.
PHPCS_SCOPE_JSON := /tmp/phpcs-scope-$(shell echo $$$$).json

# On a QUIET test run we still want the verdict, just not the per-test roll call.
TEST_SUMMARY := grep -E '^[[:space:]]*(Tests|Duration):|^OK|^OK \(|^FAILURES!|^ERRORS!|^WARNINGS!|^No tests executed'

# QUIET=1 mutes the decorative per-target banners; the gates AND the test target
# additionally go SILENT-ON-SUCCESS - they emit output ONLY when the underlying tool
# fails, and the test target prints only its final summary block.
# NOTE: silent-on-success buffers the whole run, so QUIET=1 shows no live progress
# until the command finishes or fails. Drop QUIET on a run you want to watch.
ifeq ($(QUIET),1)
BANNER := @true
else
BANNER := @echo
endif

help:
	@echo "Available commands:"
	@echo "  make syntax                 - php -l syntax check over src (or FILES)"
	@echo "  make lint                   - PHPCS PSR-12 check, report only"
	@echo "  make lint-fix               - PHPCBF, apply the fixable PSR-12 violations"
	@echo "  make cs                     - alias of lint"
	@echo "  make analyse                - PHPStan static analysis (level 6)"
	@echo "  make test                   - PHPUnit, whole suite"
	@echo "  make validate               - syntax + lint + analyse, run concurrently"
	@echo "  make validate-implementation- serial pre-merge pipeline, stops at first failure"
	@echo ""
	@echo "  FILES=\"a.php b.php\"         - scope syntax/lint/lint-fix/analyse to a file list"
	@echo "  FILTER=SomeTest             - narrow the test run to matching test class names"
	@echo "  EXEC=\"docker exec <name>\"   - run the PHP tools inside a container instead"
	@echo "  (append QUIET=1 to any target for silent-on-success: gates print only on failure,"
	@echo "   the test target prints only its final summary; drop QUIET to watch a long run)"

# FILES (optional) scopes to a list of paths; empty = every PHP file under src.
syntax:
ifeq ($(QUIET),1)
	@out="$$(files="$(FILES)"; [ -n "$$files" ] || files="$$(find src -name '*.php')"; for f in $$files; do $(EXEC) php -l "$$f" || exit 1; done 2>&1)" || { printf '%s\n' "$$out"; exit 1; }
else
	@echo "Checking PHP syntax..."
	@files="$(FILES)"; [ -n "$$files" ] || files="$$(find src -name '*.php')"; for f in $$files; do $(EXEC) php -l "$$f" || exit 1; done
endif

lint:
ifeq ($(QUIET),1)
	@out="$$($(EXEC) vendor/bin/phpcs -q --standard=phpcs.xml.dist --report=full --report-json=$(PHPCS_SCOPE_JSON) $(FILES) 2>&1)" || { printf '%s\n' "$$out"; $(MAKE) --no-print-directory lint-scope-check FILES="$(FILES)" PHPCS_SCOPE_JSON="$(PHPCS_SCOPE_JSON)"; rm -f $(PHPCS_SCOPE_JSON); exit 1; }
	@$(MAKE) --no-print-directory lint-scope-check FILES="$(FILES)" PHPCS_SCOPE_JSON="$(PHPCS_SCOPE_JSON)"
	@rm -f $(PHPCS_SCOPE_JSON)
else
	@echo "Linting (PHPCS, report-only)..."
	@$(EXEC) vendor/bin/phpcs --standard=phpcs.xml.dist --report=full --report-json=$(PHPCS_SCOPE_JSON) $(FILES)
	@$(MAKE) --no-print-directory lint-scope-check FILES="$(FILES)" PHPCS_SCOPE_JSON="$(PHPCS_SCOPE_JSON)"
	@rm -f $(PHPCS_SCOPE_JSON)
endif

# phpcs.xml.dist scopes the ruleset to <file>src</file>, so any requested path outside
# src - a test, a config file - is silently dropped and a clean exit can mean "checked
# 1 of 3" rather than "all 3 clean". Compare scanned against requested and say so.
# Warning only - out-of-scope files are a known gap, not a failure.
lint-scope-check:
	@if [ -n "$(FILES)" ]; then \
	  scanned="$$($(EXEC) php -r '$$d = json_decode(@file_get_contents("$(PHPCS_SCOPE_JSON)"), true); echo (is_array($$d) && isset($$d["files"])) ? count($$d["files"]) : 0;' 2>/dev/null)"; \
	  requested=$(words $(FILES)); \
	  if [ -n "$$scanned" ] && [ "$$scanned" -lt "$$requested" ]; then \
	    printf 'WARNING: PHPCS scanned %s of %s requested files - %s outside phpcs.xml.dist scope, NOT style-checked.\n' \
	      "$$scanned" "$$requested" "$$(( requested - scanned ))"; \
	  fi; \
	fi

# phpcbf exit codes: 0 = nothing to fix, 1 = fixes applied OK, >=2 = real error. Both 0
# and 1 are success, so the non-QUIET form needs a leading "-" to stay green, and the
# QUIET form stays silent for rc<=1 and only surfaces output on a genuine error.
lint-fix:
ifeq ($(QUIET),1)
	@out="$$($(EXEC) vendor/bin/phpcbf --standard=phpcs.xml.dist $(FILES) 2>&1)"; rc=$$?; [ $$rc -le 1 ] || { printf '%s\n' "$$out"; exit 1; }
else
	@echo "Applying PHPCBF code-style fixes..."
	-@$(EXEC) vendor/bin/phpcbf --standard=phpcs.xml.dist $(FILES)
endif

# Coding-standards gate. PHPCS is the only style tool here, so cs is an alias of lint.
cs: lint

analyse:
ifeq ($(QUIET),1)
	@out="$$($(EXEC) php -d memory_limit=-1 vendor/bin/phpstan analyse -c phpstan.neon.dist $(FILES) --no-progress 2>&1)" || { printf '%s\n' "$$out"; exit 1; }
else
	@echo "Running PHPStan static analysis..."
	@$(EXEC) php -d memory_limit=-1 vendor/bin/phpstan analyse -c phpstan.neon.dist $(FILES) --no-progress
endif

# FILTER (optional) narrows the run to matching test class names; FILES (optional)
# scopes it to a list of paths; both empty = whole suite. phpunit.xml.dist declares a
# single "default" suite, so there is no per-suite target to emit alongside this one.
# --colors=never on the QUIET path is load-bearing: phpunit.xml.dist sets colors="true",
# which wraps the "OK (...)" verdict in ANSI codes that TEST_SUMMARY would never match.
test:
ifeq ($(QUIET),1)
	@out="$$($(EXEC) php -d memory_limit=-1 vendor/bin/phpunit --no-coverage --colors=never $(if $(FILTER),--filter="$(FILTER)") $(FILES) 2>&1)" || { printf '%s\n' "$$out"; exit 1; }; printf '%s\n' "$$out" | $(TEST_SUMMARY) || true
else
	@echo "Running test suite..."
	@$(EXEC) php -d memory_limit=-1 vendor/bin/phpunit --no-coverage $(if $(FILTER),--filter="$(FILTER)") $(FILES)
endif

# Runs the independent gates concurrently (own sub-make with -j).
validate:
ifeq ($(QUIET),1)
	@$(MAKE) --no-print-directory -j syntax lint analyse FILES="$(FILES)" QUIET=1
else
	@echo "Running the static gates concurrently..."
	@$(MAKE) --no-print-directory -j syntax lint analyse FILES="$(FILES)"
endif

# Pre-merge pipeline. SERIAL and ordered on purpose, cheapest gate first, so the first
# failure stops the run - the opposite of `validate`, which fans out.
validate-implementation:
	@$(MAKE) --no-print-directory syntax FILES="$(FILES)" QUIET=$(QUIET)
	@$(MAKE) --no-print-directory analyse FILES="$(FILES)" QUIET=$(QUIET)
	@$(MAKE) --no-print-directory cs FILES="$(FILES)" QUIET=$(QUIET)
	@$(MAKE) --no-print-directory test QUIET=$(QUIET)
