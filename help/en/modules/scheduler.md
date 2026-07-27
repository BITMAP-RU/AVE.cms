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

After changing the secret URL, the old address immediately stops working.
