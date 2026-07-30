# Scheduler

The `scheduler` module collects background tasks of different modules in one place. He doesn't
requires CLI: the panel shows a secure HTTP URL that can be called from
hosting scheduler once per minute.

In the **Modules → Scheduler** section, you can schedule, enable a task,
nearest launch, manual launch and log. A cron expression consists of five parts:
`minute hour day month day-week`. For example, `*/15 * * * *` - every 15 minutes.

The module registers the task with the filter `scheduler.tasks`:

```php
public static function tasks(array $payload)
{
    $payload['tasks']['my_module.cleanup'] = array(
        'title' => 'Очистка данных',
        'description' => 'Удаляет устаревшие записи.',
        'module' => 'my_module',
        'cron' => '30 3 * * *',
        'timeout' => 120,
        'handler' => array(Cleanup::class, 'run'),
    );
    return $payload;
}
```

The handler receives `task`, `trigger`, and `run_id`. It can return an array with
`message` and metrics. Parallel launch of one task is blocked. Events
`scheduler.task.started`, `scheduler.task.completed` and `scheduler.task.failed`
allow you to connect notifications or your own audit.

## Long-running tasks

Do not keep an endless loop inside a handler when the work cannot fit into one
short request. Set `chunked => true` and process a small batch per call:

```php
$payload['tasks']['my_module.rebuild'] = array(
    'title' => 'Rebuild data',
    'module' => 'my_module',
    'cron' => '20 2 * * 0',
    'enabled' => false,
    'chunked' => true,
    'handler' => array(RebuildTask::class, 'run'),
);
```

The handler also receives `state` and `progress`. Return the following contract:

```php
return array(
    'done' => $finished,
    'state' => array('after_id' => $nextId),
    'progress' => array(
        'current' => $processed,
        'total' => $total,
        'label' => 'Documents',
    ),
    'message' => 'Processed: ' . $processed,
    'result' => array('updated' => $updated),
);
```

The scheduler persists `state`, releases the lock, and resumes the same run on
the next secure tick. The log contains one run instead of one row per chunk.
An active run can be cancelled from the task table or the run log. Already
processed domain data is not rolled back; a new run starts the module scenario
again.

`enabled => false` only defines the initial state of a newly discovered task.
It is useful for heavy maintenance launched manually. A manually started active
run is still resumed by the secure scheduler tick even when its recurring
schedule is disabled. Without a configured HTTP tick, use the play button in
the task table to process the next chunk manually.

The `scheduler.task.progress` event is emitted after every unfinished chunk and
contains the persisted `state` and `progress`.

After changing the secret URL, the old address immediately stops working.
