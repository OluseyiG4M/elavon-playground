# Elavon playground

A playground for the Elavon API. Basic local front-end, generated clients for the two APIs, and transaction controllers

Run using 
```bash 
docker-compose up --build
```
```bash 
docker/tools/composer install
```
### Note if you regenerate the client
In [ObjectSerializer.php](src/V1/EPG/ObjectSerializer.php)::deserialize(),
Add this snippet to fix error with classes without namespace
```php
 // Auto-prefix namespace for Model classes if not already prefixed
    if ($class && 
        $class[0] !== '\\' && 
        !in_array($class, ['DateTime', 'array', 'bool', 'boolean', 'byte', 'double', 'float', 'int', 'integer', 'mixed', 'number', 'object', 'string', 'void'], true) &&
        strpos($class, '\\') === false &&
        !preg_match('/^(array<|map\[)/', $class) &&
        strcasecmp(substr($class, -2), '[]') !== 0) {
        $class = '\\Gear4music\\ElavonPlayground\\V1\\EPG\\Model\\' . $class;
    }
```


### How to regenerate
From the root folder run:
```bash 
docker run --rm \
  -v "${PWD}/src:/out/src" \
  -v "${PWD}/docs:/out/docs" \
  openapitools/openapi-generator-cli generate \
  -i https://<PERSONAL ACCESS TOKEN>@raw.githubusercontent.com/ElliotG4M/elavon-playground/refs/heads/master/res/openAPI/EPG.json?token=<AUTO GENERATED TOKEN FROM GITHUB> \
  -g php \
  -o /out \
  --additional-properties "invokerPackage=Gear4music\ElavonPlayground\V1\EPG,srcBasePath=src/V1/EPG" \
  --api-package ElavonPlayground
```

```bash 
docker run --rm \
  -v "${PWD}/src:/out/src" \
  -v "${PWD}/docs:/out/docs" \
  openapitools/openapi-generator-cli generate \
  -i https://<PERSONAL ACCESS TOKEN>@raw.githubusercontent.com/ElliotG4M/elavon-playground/refs/heads/master/res/openAPI/PMG.yaml?token=<AUTO GENERATED TOKEN FROM GITHUB> \
  -g php \
  -o /out \
  --additional-properties "invokerPackage=Gear4music\ElavonPlayground\V1\PMG,srcBasePath=src/V1/PMG" \
  --api-package ElavonPlayground
```

