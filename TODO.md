RedMap TODO list
================

TODO
----

- Define parameter macro in client, not engine
- Implement new PDO client
- Replace FIELD_INTERNAL by naming convention e.g. ".name"
- Fix SQL error when missing explicit link on nested sourced data [source-nested-implicit]

DONE
----

- Make table name escaping consistent in redmap
- Remove FIELD_PRIMARY and update set/copy signatures to pass explicit filters and assignments
- Factorize common code between "ingest" and "insert" methods
- Split method `clean` into separate methods
