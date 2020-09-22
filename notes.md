- [x] Fakers for nullable columns that have foreign keys are currently always generating a non-null value, but sometimes they should fake a null!

- [x] When generating a model to test uniqueness, we should:
  - use the full_model state
  - not generate custom values for columns with foreign keys (let the factory generate the necessary models for us)

- [x] On factories, the full_model state should generate foreign models also in the full_model state

- [ ] Requests with time columns containing the value '25:00:00' are somehow being accepted. Why?

- [x] Column fakers for types other than string or text should not use custom faker methods, but always the one for that type

- [x] In the migration generator, foreign key columns cannot have a default value
  - [x] this should be an error when reading the database.

- [ ] On models that have no specific need for the full_model (it still needs to exist!), simplify the state definition.

- [x] On factories that need to generate a model on a unique column, there is no need to specify the unique identifier, as the value in the column will always be unique because we are already creating and persisting the child model.

- [ ] On stub replacement, see if we can replace with a multi-line string, which must necessarily be correctly indented

- [x] Primary keys that span multiple columns should be an error when reading the database

- [x] Do not create views for pivot tables (the problem was that we had a wrong definition of pivot and were generating pivots with soft deletes.)

- [ ] Columns with foreign keys should be dropdown boxes in the create/edit views

- [ ] Pivot tables should mean that one of the models has a dropdown for the other model

- [x] Generated pivot tables must not have unique/nullable modifiers.

- [x] Improve the way to generate migrations and to run the autocrud commands in the testing environment

- [ ] On generated migrations, do not allow "weird" relationships. For example, table A, column X refers to id of table B, and then there is a pivot for tables A and B...

- [ ] When asserting fields, if the model has a unique constraint on a boolean column, the method `beginAssertFields` must explicitly use the boolean value `false` associated with that column since the model created to check for uniqueness will already have the value `true`.

- [ ] On generated migrations, date-like columns should sometimes have a default value other than `CURRENT_TIMESTAMP`

- [x] Fix model not being created in the `assertFields` method when the only UNIQUE columns are foreign keys
