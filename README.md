# Arango-ODM
This library is an PHP ODM for ArangoDB. If offers a lot of helpful tools to work with your ArangoDB in your PHP-Project.

# Warning: Early Development - The usage of this lib may change frequently!

# What are the main features of the library?
- [x] use replaceable adapters to access the database (atm only curl/http)
- [x] use the DocumentHandler to add, update, delete, find or query with the database
- [x] use ArangoDB edge-collections to connect your documents
- [x] use Document-Getters to access connected Documents
- [x] use Document-Setters to change connections
- [x] create your collections using ArangoDB webinterface, let the ODM lib create matching Document-Classes to work with
- [x] get Document-Objects of the matching custom class back as db-results
- [ ] optimized internal performance with help of performance benchmarks
- [ ] improved documentation
- [ ] PHPUnit tests
- [ ] socket-adapter

# How can i help?
* You are welcome to test it and give issues to improve it

# My personal benchmark-results using this library
* Insert 1,3k and 1k documents in two different collections in 1,06 sec (no bulk insert. Each document a single request)
* Insert 1 mio documents bulk: ~11 sec
* Get the inserted 1,3k and 1k from the two different collections as Document objects in 0,04 sec
* Connect 1,3k with 1k in edge collection in ~25 sec (means to create 1,3 mio edges)
* Get the first 50k of the created edges in 1,02 sec
* Get all 1,3 mio edges in ~28 sec