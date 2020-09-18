- [ ] Fakers for nullable columns that have foreign keys are currently always generating a non-null value, but sometimes they should fake a null!

- [ ] When generating a model to test uniqueness, we should:
  - use the full_model state
  - not generate custom values for columns with foreign keys (let the factory generate the necessary models for us)

- [ ] On factories, the full_model state should generate foreign models also in the full_model state

- [ ] Requests with time columns containing the value '25:00:00' are somehow being accepted...

- [x] Column fakers for types other than string or text should not use custom faker methods, but always the one for that type
