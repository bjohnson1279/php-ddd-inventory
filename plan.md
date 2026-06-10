1. Use `replace_with_git_merge_diff` to modify `src/Infrastructure/Persistence/Repositories/EloquentProductRepository.php` to use `ProductLocationModel::upsert` instead of a loop calling `ProductLocationModel::updateOrCreate` when the DB connection driver is not `sqlite`.
2. Run the full test suite using `run_in_bash_session` with the command `php vendor/bin/phpunit` to ensure no regressions are introduced.
3. Complete pre-commit steps to ensure proper testing, verification, review, and reflection are done.
4. Use the `submit` tool to create the PR with the title '⚡ Bolt: Optimize product location bulk saves' and include the required description sections.
