---
description: "Generate PR execution plan from review summary"
argument-hint: "<path-to-pr-review-summary.md>"
---

Read the PR review summary file at path: $1

Extract all PR titles and their affected files from the file. The file contains sections with:
- PR titles in format "## PR #XX: <title>"
- Affected Files sections listing file paths

Create a mapping table where:
- Each unique affected file appears in Column 1
- All PR titles that modify that file appear in Column 2 (comma-separated if multiple)

Generate a markdown file with:
1. Header: "# Pull Request Execution Plan"
2. A line showing the generation date in format "**Generated**: YYYY-MM-DD"
3. A table with headers "| Affected File | PR Titles |"
4. Table rows sorted alphabetically by file path

Write the output to: `pr-execution-plan-{today}.md` where {today} is today's date in YYYY-MM-DD format (e.g., 2025-10-01).

Example output format:
```markdown
# Pull Request Execution Plan

**Generated**: 2025-10-01

| Affected File | PR Titles |
|---------------|-----------|
| path/to/file1.json | PR #64: Bump package A, PR #62: Bump package B |
| path/to/file2.csproj | PR #63: Update dependency X |
```
