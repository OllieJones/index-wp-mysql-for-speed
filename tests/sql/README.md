# SQL for testing

Here are SQL scripts for settng up tests.

To run a script on the database named `exampledb` use this


```shell
mysql --user=user --password=REDACTED exampledb < script.sql
```

## To test the dectection of 3.3 to 4.1 upgrading:

1. use the plugin or wpcli to put in WordPress standard keys, undoing anything that's there.
2. if you're on a Barracuda database do this.

   ```shell
   mysql --user=user --password=REDACTED exampledb < convert-DDL_standard-to-1.3.3-Barracuda.sql
   ```
3. Then the plugin 4.1 should see the upgrade situation.
