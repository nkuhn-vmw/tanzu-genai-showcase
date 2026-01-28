# Git Commit

Execute git diff to analyze all changes. Summarize and articulate all changes into a suitable git commit message following conventional commit format. Then stage all changes with git add -A and create the commit with that message.

## Usage

```text
/git-commit
```

## Description

This command automates the git commit process by:

1. Running `git diff` to analyze all unstaged changes
2. Creating a descriptive commit message based on the changes
3. Staging all changes with `git add -A`
4. Committing with the generated message

The commit message will follow conventional commit format and accurately describe the nature and purpose of the changes.
