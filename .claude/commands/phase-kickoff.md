---
allowed-tools: Read, Glob, Grep, Task, TodoWrite, Bash(make:*)
argument-hint: 1|2|3|4
description: Start a new development phase for Emailibrium
---

# Phase Kickoff

Starting Phase $1 implementation for Emailibrium:

1. Load the feature implementation priority matrix from `.project/features/features-implementation-priority-matrix.md`
2. Identify all P0 (Must Have) features for Phase $1
3. Check feature dependencies in `.project/features/features-dependencies-map.md`
4. Create a parallel implementation plan for all P0 features
5. Spawn specialized agents for each feature using the Task tool
6. Set up monitoring and coordination between agents
7. Create phase-specific test suite

## Phase Definitions

- **Phase 1**: MVP Implementation (Months 1-3)
- **Phase 2**: AI Integration (Months 4-6)
- **Phase 3**: Multi-Account Management (Months 7-9)
- **Phase 4**: Advanced Features (Months 10-12)

## Implementation Strategy

Use parallel Task execution for independent features and coordinate via TodoWrite tool for progress tracking.
