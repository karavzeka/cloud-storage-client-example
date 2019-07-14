The client for working with cloud storage.

**This is example code. It doesn't contain dependencies, so it doesn't work**

Example of usage:
```php
<?php

$factory = \App::getContainer()->get(SelectelClientFactory::class);
$client = $factory->getStaticStorageClient();

// Uploading a file to the storage
$client->uploadFile('/var/tmp/product_instruction.pdf', '/instructions/10.pdf');

// Making a link inside the storage
$client->makeLink('/instructions/10.pdf', '/instructions/10-link.pdf');

// Deleting a file in the storage
$client->deleteFile('/instructions/10-link.pdf');
```
