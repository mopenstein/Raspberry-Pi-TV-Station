# Database Maintenance Workers

PHP-based maintenance utilities designed to prune or truncate station database tables (`played`, `commercials`, `errors`). These scripts can be executed manually via HTTP GET requests or scheduled automatically by the station's main automation runner.

---

## Response Format

Worker scripts communicate execution status using pipe-delimited strings:

```text
<STATUS_CODE>|<MESSAGE>
```

* `1`: Operation succeeded (e.g., `1|Trim complete for table 'played'...`).
* `0`: Operation failed (e.g., `0|Invalid target table specified...`).

---

## Available Workers

### 1. `trim_by_days.php`
Deletes rows older than a specified number of days based on their `played` epoch timestamp. Cutoff points are calculated relative to midnight (`midnight -$dayOffset days`).

* **Allowed Targets**: `played`, `errors`, `commercials`
* **Parameters**:
  * `target` (required): The table to prune.
  * `days` (optional, default: `2`): Number of recent days to preserve. Minimum value is `1`.

**Example:**
```text
[http://127.0.0.1/workers/trim_by_days.php?target=commercials&days=5](http://127.0.0.1/workers/trim_by_days.php?target=commercials&days=5)
```

---

### 2. `trim_played_by_most_recent.php`
Prunes the `played` table on a per-directory basis. It identifies all unique file parent paths and retains only the newest entries for each directory.

* **Parameters**:
  * `count` (optional, default: `5`): Maximum recent entries to keep per subdirectory. Minimum value is `1`.
  * `skip_paths` (optional): Pipe-separated list (`|`) of directory substrings to ignore during pruning.

**Example:**
```text
[http://127.0.0.1/workers/trim_played_by_most_recent.php?count=5&skip_paths=bumpers](http://127.0.0.1/workers/trim_played_by_most_recent.php?count=5&skip_paths=bumpers)|station_ids
```

---

### 3. Truncate Scripts
Direct cleanup scripts that immediately empty an entire table using `TRUNCATE TABLE`. These do not accept parameters.

| Script | Target Table | Description |
| :--- | :--- | :--- |
| `empty_played.php` | `played` | Completely flushes playback history. |
| `empty_errors.php` | `errors` | Completely clears error logs. |
| `empty_commercials.php` | `commercials` | Completely flushes logged commercial data. |

---

## Automation Setup

Define worker URLs in `settings.json` under the `"workers"` array. Paths are relative to `http://127.0.0.1/`.

```json
{
  "workers": [
    "workers/trim_played_by_most_recent.php?count=5",
    "workers/trim_by_days.php?days=5&target=commercials",
    "workers/trim_by_days.php?days=5&target=errors"
  ]
}
```

The TV station orchestrator queries this list, invokes each endpoint via local HTTP, and logs status codes (`1` for `WORKER_SUCCESS`, `0` for `WORKER_FAIL`, or `WORKER_TIMEOUT` if the server fails to respond).
