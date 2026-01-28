---
allowed-tools: Bash(git *), Write, mcp__github__list_pull_requests, mcp__github__get_pull_request, mcp__github__get_pull_request_files, mcp__github__get_pull_request_diff
argument-hint: "[owner/repo]"
description: Review all open pull requests and generate a comprehensive summary report
---

# Pull Request Review Summary

Generate a comprehensive summary of all open pull requests for the specified repository (or current repository if not specified) and save it to a markdown file named `pr-review-summary-{today}.md` where {today} is the current date in YYYY-MM-DD format (e.g., 2025-10-01).

## Instructions

1. **Determine the repository**:
   - If an argument is provided in the format `owner/repo`, use that
   - Otherwise, extract the owner and repo from the current git repository's remote origin URL using `git config --get remote.origin.url`

2. **Fetch open pull requests with pagination**:
   - Use `mcp__github__list_pull_requests` with state: "open" and perPage: 10
   - Process PRs in batches to manage context

3. **Context Management Strategy**:
   - **Process PRs one at a time** - never load all PRs into context simultaneously
   - **Use pagination**: Process maximum 10 PRs per batch
   - **Incremental writing**: Append to the summary file after processing each PR instead of building everything in memory
   - **Diff size limits**: For large diffs (>1000 lines), provide a high-level summary instead of full details
   - **File list limits**: If a PR has >20 files, group similar files (e.g., "15 test files", "8 component files")

4. **For each pull request, gather** (one at a time):
   - Full title and PR number using `mcp__github__get_pull_request`
   - Get affected files using `mcp__github__get_pull_request_files` (perPage: 30)
   - Get change details using `mcp__github__get_pull_request_diff`
   - PR URL (https://github.com/{owner}/{repo}/pull/{number})
   - **Immediately write this PR's section** to the file before moving to the next PR

5. **Generate the summary file**:
   - File name: `pr-review-summary-{today}.md` (use today's date: 2025-10-01)
   - **Create file header first** with repository info and placeholder for total count
   - **Append each PR section** as you process it
   - **Update total count** at the end if needed
   - Format the content as follows:

```markdown
# Pull Request Review Summary

**Repository**: {owner}/{repo}
**Review Date**: {today}
**Total Open PRs**: {count}

---

## PR #{number}: {full title}

**Link**: {pr_url}

### Affected Files
- {file1}
- {file2}
- ...

### Change Details
{summary of changes from the diff}

---

{repeat for each PR}
```

6. **Writing Strategy**:
   - Use **Write tool** to create the initial file with header
   - Use **Edit tool with append** or **multiple Write operations** to add each PR section incrementally
   - This prevents context overflow by not holding all PR data in memory

## Usage Examples

```text
# Review PRs in current repository
/pr-review

# Review PRs in a specific repository
/pr-review octocat/hello-world
```

## Notes

- **CRITICAL**: Process PRs one at a time and write incrementally to avoid exceeding 25k token context limit
- Use pagination (perPage: 10) when listing PRs
- For large diffs (>1000 lines), summarize changes at a high level instead of including full diff text
- If a PR has >20 files, group similar files to reduce output size
- Include meaningful summaries of changes, not raw diffs
- If there are no open PRs, indicate this in the summary file
- Consider using TodoWrite tool to track progress when processing many PRs
