# Delete Duplicate Post Meta Fields - WP-CLI-Command
A custom WP-CLI command to delete/export duplicate post meta fields.

# Options
- `[--dry-run]` - If set, no duplicate meta fields will be deleted.
- `[--post_id=<post_id>]` - If set, only the duplicate meta fields for the given post will be checked.
- `[--export=<export>]` - If set, the duplicate meta fields will be exported to a CSV file. Options:
    - `[none]` or `count` (default): Exports the count of duplicate meta fields.
    - `values`: Exports the duplicate meta values with their values.

# Notes
- If `--dry-run` isn't used, all the postmeta entries with the same `post_id`, `meta_value` **and** `meta_key` will be deleted.
- Any entries with the same `post_id` and `meta_key` but **with different `meta_value` won't be deleted**, leaving them in the database for a manual review.
- All exported CSV files will be stored in the `wp-content/uploads/exports` folder.

# Examples
- `wp delete-duplicate-meta --dry-run`

Checks how many duplicate post meta keys exist in the database.
  
- `wp delete-duplicate-meta --export=values --dry-run`

Exports a list of the duplicate post meta keys with their values.

- `wp delete-duplicate-meta --post_id=123 --export`

Deletes the duplicate meta keys of the post 123 and exports a list of the duplicate keys with a count of duplicate values.

- `wp delete-duplicate-meta --export=values`

Deletes the duplicate meta keys of all the posts and exports:
- A list of the duplicate keys with a count of duplicate values
- A list of duplicated keys with their values.

# Use Case
Let's say we have a database that due to a faulty import has multiple postmeta entries with the same meta_key and meta_values for the same posts. In this case we can:
1. Run `wp delete-duplicate-meta --dry-run  --export` to export a list of all the meta_keys duplicated for each post
2. Run `wp delete-duplicate-meta --dry-run  --export=values` if we want to get a full list of these duplicated meta_keys with their meta_value (not recommended if there are a large amount of duplicated entries)
3. Run `wp delete-duplicate-meta --export=values` to delete all the duplicated entries and get new CSV files with the remaining duplicate meta_keys and meta_values (in case there are duplicated meta_keys with different meta_values).
