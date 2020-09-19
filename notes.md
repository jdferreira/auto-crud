- [x] Fakers for nullable columns that have foreign keys are currently always generating a non-null value, but sometimes they should fake a null!

- [x] When generating a model to test uniqueness, we should:
  - use the full_model state
  - not generate custom values for columns with foreign keys (let the factory generate the necessary models for us)

- [x] On factories, the full_model state should generate foreign models also in the full_model state

- [ ] Requests with time columns containing the value '25:00:00' are somehow being accepted. Why?

- [x] Column fakers for types other than string or text should not use custom faker methods, but always the one for that type

- [ ] In the migration generator, foreign key columns cannot have a default value. This should be an error when reading the database.

- [ ] On models that have no specific need for the full_model (it still needs to exist!), simplify the state definition.

- [x] On factories that need to generate a model on a unique column, there is no need to specify the unique identifier, as the value in the column will always be unique because we are already creating and persisting the child model.

- [ ] On stub replacement, see if we can replace with a multi-line string, which must necessarily be correctly indented

- [ ] Primary keys that span multiple columns should be an error when reading the database
