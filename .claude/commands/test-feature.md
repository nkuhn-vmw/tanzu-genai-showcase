---
allowed-tools: Read, Grep, Bash(make test:*), Bash(cargo test:*), Bash(pnpm test:*), Bash(cargo bench:*), WebFetch
argument-hint: FEAT-XXX
description: Run comprehensive tests for a specific feature
---

# Test Feature

Test feature $1 comprehensively across the entire stack:

1. Run unit tests for the feature in both backend and frontend
2. Execute integration tests
3. Test against requirements defined in the feature specification
4. Validate against success metrics in `.project/features/features-success-metrics-and-kpis.md`
5. Check performance benchmarks using `cargo bench` for backend
6. Verify UI/UX against design mockups in `.project/design/`
7. Run security checks if applicable using `make security-audit`

## Test Execution Order

1. **Unit Tests**: `cargo test` for Rust, `pnpm test` for TypeScript
2. **Integration Tests**: `make test-integration`
3. **E2E Tests**: `make test-e2e`
4. **Performance**: `make bench`
5. **Security**: `make security-audit`

## Success Criteria

All tests must pass before marking the feature as complete. Document any test failures for follow-up fixes.
