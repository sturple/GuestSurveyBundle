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

**Add to ```app/AppKernel.php``` file**

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

**Add to routes.yml**

```yaml
fgms_survey:
    resource: "@FgmsSurveyBundle/Resources/config/routing.yml"
    prefix:   /
```

**Add to config.yml**

```yaml
doctrine:
    orm:
        dql:
            numeric_functions:
                Rand: Fgms\Bundle\SurveyBundle\Utility\DoctrineRand
```

**Create ```survey.yml``` in your config directory

```yaml
images_path: /web/assets/images/
property_config_dir: "/../../config/property/"
email:
    from:
        address: 'guest.response@example.com'
        name: Guest Feedback

```