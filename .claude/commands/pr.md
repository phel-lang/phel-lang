Create a pull request for the current branch.

1. Read `.github/PULL_REQUEST_TEMPLATE.md` and use its structure for the PR body
2. Analyze all commits on this branch (vs main) to write the PR title and body
3. Push the branch with `git push -u origin HEAD`
4. Create the PR:
   - Use `gh pr create`
   - Add `--assignee "@me"`
   - Pick labels from: `bug`, `enhancement`, `refactoring`, `documentation`, `pure testing`, `dependencies`
   - Follow the template structure in the body
5. Return the PR URL

$ARGUMENTS contains optional context for the PR description.
