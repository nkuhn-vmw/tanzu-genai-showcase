---
allowed-tools: Read, Glob, Grep, Bash(make test:*), Task, TodoWrite
argument-hint: [optional: phase number]
description: Run a daily development standup for Emailibrium project
---

# Daily Standup

Daily development standup for the Emailibrium project:

1. Check progress on current phase features (Phase $1 if specified)
2. Review any blocked items in the todo list
3. Update the todo list with current status
4. Run tests for recently completed features
5. Check memory for cross-agent coordination issues (if using claude-flow)
6. Plan today's parallel tasks using the Task tool
7. Identify dependencies for tomorrow's work

## Review Checklist

- [ ] All P0 features for current phase on track?
- [ ] Any blockers that need immediate attention?
- [ ] Tests passing for completed features?
- [ ] Documentation updated for new features?
- [ ] Performance benchmarks within acceptable limits?
- [ ] Security scans run on new code?

## Output Format

Provide a brief summary of:

- **Completed**: Features finished since last standup
- **In Progress**: Currently active work
- **Blocked**: Items needing attention
- **Today's Focus**: Priority tasks for today
