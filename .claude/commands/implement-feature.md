---
allowed-tools: Read, Write, Edit, Glob, Grep, Bash(make test:*), Bash(cargo test:*), Bash(pnpm test:*), Task, TodoWrite
argument-hint: FEAT-XXX
description: Implement a feature from the Emailibrium project specs
---

# Implement Feature

Implement feature $1 from the Emailibrium project following these steps:

1. Read the feature specification from `.project/features/*/$1.md`
2. Check feature dependencies in `.project/features/features-dependencies-map.md`
3. Review the current tech stack in `.project/tech-stack/current/`
4. Follow development patterns defined in `CLAUDE.md`
5. Apply Test-Driven Development (TDD) approach - write tests first
6. Create implementation in the appropriate location:
   - Backend: `backend/src/`
   - Frontend: `frontend/apps/desktop/` or `frontend/apps/web/`
7. Run tests with `make test`
8. Update relevant documentation

## Context

Reference the feature implementation priority matrix at `.project/features/features-implementation-priority-matrix.md` to understand priority level (P0/P1/P2) and phase assignment.
