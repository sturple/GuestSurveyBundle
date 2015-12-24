# Guest Survey Bundle

## Installation


** Method 1 Composer **
```json
{
    "require": {
        "sturpe/guest-survey-bundle": "dev-master"
    }
}

```

and then execute

```json
$ composer update
```

** Method 2 Composer **
```json
composer.phar require sturple/guest-survey-bundle:dev-master
```

## Configuration

** Add to ```app/AppKernal.php``` file

```php
class AppKernel extends Kernel
{
    public function registerBundles()
    {
        $bundles = array(
            // .. other bundles            
            new Fgms\Bundle\SurveyBundle\FgmsSurveyBundle(),
        );
    }
}
```

** Add to routes.yml **

```yaml
fgms_survey:
    resource: "@FgmsSurveyBundle/Resources/config/routing.yml"
    prefix:   /
```