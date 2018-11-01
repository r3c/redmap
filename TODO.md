RedMap TODO list
================

TODO
----

- Implement PDO client
- Replace FIELD_INTERNAL by naming convention e.g. ".name"
- Fix SQL error when missing explicit link on nested ingested data [ingest-nested-implicit]

DONE
----

- Make table name escaping consistent in redmap
- Remove FIELD_PRIMARY and update set/copy signatures to pass explicit filters and assignments
- Factorize common code between "ingest" and "insert" methods
