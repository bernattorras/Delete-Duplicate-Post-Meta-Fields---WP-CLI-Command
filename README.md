# Delete Duplicate Post Meta Fields - WP-CLI-Command
A custom WP-CLI command to delete/export duplicate post meta fields.

# Options
- `[--dry-run]` - If set, no duplicate meta fields will be deleted.
- `[--post_id=<post_id>]` - If set, only the duplicate meta fields for the given post will be checked.
- `[--export=<export>]` - If set, the duplicate meta fields will be exported to a CSV file. Options:
    - `[none]` or `keys` (default): Exports a CSV file with a list of the posts with duplicate meta_keys including their post_id and the number of duplicate keys (count).
    - `values`: Exports a CSV file with the keys (previous case) and another one with the values of the duplicated meta_keys.

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

**Example of the exported `duplicate_meta_keys` file:**
```
|   post_id  |   meta_key             |   duplicate_keys_count  |
|------------|------------------------|-------------------------|
|   36       | example_meta_key       |   16                    |
|   36       | a_different_meta_key   |   23                    |
|   54       | example_meta_key       |   54                    |
|   ...      |   ...                  |   ...                   |
```


2. Run `wp delete-duplicate-meta --dry-run  --export=values` if we want to get a full list of these duplicated meta_keys with their meta_value (not recommended if there are a large amount of duplicated entries)

**Example of the exported `duplicate_meta_values` file:**
```
|   meta_id  |   post_id             |   meta_key  |   meta_value      |
|------------|-----------------------|-------------|-------------------|
|   36       | example_meta_key      | 1           | value 1           |
|   36       | example_meta_key      | ...         | [repeated_values] |
|   36       | example_meta_key      | 16          | value 16          |
|   36       | a_different_meta_key  | 1           | value 1           |
|   36       | a_different_meta_key  | ...         | [repeated_values] |
|   36       | a_different_meta_key  | 23          | value 23          |
|   54       | example_meta_key      | 1           | value 1           |
| ...        | ...                   | ...         | ...               |
```


3. Run `wp delete-duplicate-meta --export=values` to delete all the duplicated entries and get new CSV files with the remaining duplicate meta_keys and meta_values (in case there are duplicated meta_keys with different meta_values).

**Example of the exported `duplicate_meta_keys` file:**
```
|   post_id  |   meta_key             |   duplicate_keys_count  |
|------------|------------------------|-------------------------|
|   36       | example_meta_key       |   2                     |
|   54       | example_meta_key       |   3                     |
|   ...      |   ...                  |   ...                   |
```
_Now there are only 2 remaining duplicates of the `example_meta_key` for the post #36 (with different values) and 3 for the post #54._


**Example of the exported `duplicate_meta_values` file:**
```
|   meta_id  |   post_id             |   meta_key  |   meta_value  |
|------------|-----------------------|-------------|---------------|
|   36       | example_meta_key      | 1           | value 1       |
|   36       | example_meta_key      | 2           | value 2       |
|   54       | example_meta_key      | 1           | value 1       |
|   54       | example_meta_key      | 1           | value 2       |
| ...        | ...                   | ...         | ...           |
```
_This CSV only includes the post's repeated meta_keys, which have now different (unique) meta_values. These entries should be reviewed manually to determine which ones need to be deleted (if they aren't needed)._
