# Transactions

← [Back to DB section](README.md)

Transactions ensure that a group of changes is applied "all or nothing".

---

## Methods

```php
DB::startTransaction();   // начать
DB::commit();             // подтвердить
DB::rollback();           // откатить
```

Nested transactions are supported (via `nested_transactions` - internal
levels are emulated by the savepoint logic of the transaction manager).

### Action after commit

External HTTP, sending emails, posting to social networks and other irreversible actions
do not run until the database is confirmed. Register the callback:

```php
DB::startTransaction();
try {
    DB::Insert($orders, $order);
    DB::afterCommit(function () use ($order) {
        OrderNotifications::created($order);
    });
    DB::commit();
} catch (\Throwable $e) {
    DB::rollback();
    throw $e;
}
```

Inside the transaction, the callback is executed after the successful external `commit()`. When
`rollback()` it is being deleted. If there is no active transaction, `DB::afterCommit()`
executes the callback immediately. The callback exception is logged and cannot be canceled
already confirmed transaction, so the integration must be idempotent.

---

## Basic pattern

Always wrap the body in `try/catch` and rollback on the error:

```php
DB::startTransaction();
try {
    DB::Insert($orders, $order);
    $orderId = (int) DB::insertId();

    foreach ($items as $item) {
        $item['order_id'] = $orderId;
        DB::Insert($orderItems, $item);
    }

    DB::Update($stock, array('qty' => DB::sqlEval('qty - 1')), 'id = %i', $productId);

    DB::commit();
} catch (\Throwable $e) {
    DB::rollback();
    throw $e;   // или обработать/залогировать
}
```

---

## When needed

- Several related entries must be applied together (order + items + balances).
- Batch operations where partial results are not allowed.
- Transfer/recalculation of data.

## When not needed

- Single `INSERT`/`UPDATE`/`DELETE` - it is already atomic.
- Read only (`SELECT`).

---

## Pitfalls

- **Don't forget `commit()`** - without it, changes will be rolled back upon completion of the request.
- Do not mix with DDL (`ALTER`, `CREATE`) - in MySQL they cause an implicit commit.
- Keep the transaction **short**: do not make external HTTP calls inside it and
  long operations - this keeps the blocking.
