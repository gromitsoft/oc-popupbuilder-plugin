# Popups plugin

Plugin provides PopupController controller behavior for easy creating popups on OctoberCMS backend pages.

## Creating popups

Add PopupController behavior to your controller.

```php
use GromIT\PopupBuilder\Behaviors\PopupController;

class MyController extends Controller
{
    public $implement = [
        PopupController::class
    ]; 
    
    public $popupConfig = 'config_popup.yaml'; 
}
```

You can add multiple configurations.

```php
public $popupConfig = [
    'popup1' => 'config_popup_1.yaml',
    'popup2' => 'config_popup_2.yaml',
]; 
```

For render open popup button call ```popupRenderOpenBtn()``` method from controller with definition as optional parameter.  

```php
<?= $this->popupRenderOpenBtn('popup1') ?>
```

Or you can write html by yourself.

```html
<button class="btn btn-primary"
        data-control="popup"
        data-handler="onOpenPopup"
        data-size="medium"
        data-request-data="popupDefinition: 'popup1'">
    Open popup
</button>
```

## Popup types

PopupController supports 3 types of popups: **content**, **msg** and **form**.

Content Popup is popup with static content.

Msg popups is popup with message like static flash message.

Form popup is popup with custom form.

## Popup config

### Common options

Common options for popups of all types:

Option           | Description | Default value
-----------------|-------------|---------------
**type**         | Popup type. Possible values: ```content```, ```form``` or ```msg```. | &nbsp;
**openBtnLabel** | Open popup button label. | &nbsp;
**openBtnClass** | Open popup button css class. | btn btn-default
**popupSize**    | Size of popup. Available sizes: ```giant```, ```huge```, ```large```, ```medium```, ```small```, ```tiny```. | medium
**noPadding**    | Remove padding from popup body. | &nbsp;
**popupId**      | Popup Id attribute. | definition name 
**inset**        | Do not wrap popup in div with id. | false 

**type** is required for every popup config.

**openBtnLabel** is required for render open popup button.

### Msg

Option             | Description | Default value
-------------------|-------------|---------------
**msgType**        | Message type. Available values: ```success```, ```info```, ```warning```, ```danger``` | info
**content**        | Message text | &nbsp;

Config for msg popup must contain both of these options.

### Content

Option             | Description
-------------------|------------
**title**          | Popup title.
**content**        | Popup content.
**contentPartial** | Partial name with popup content. Overrides **content** property.

Config for content popup must contain **content** or **contentPartial**.

### Form

Option                  | Description | Default value
------------------------|-------------|---------------
**title**               | Popup title. | &nbsp;
**content**             | Popup content. Renders above the form. | &nbsp;
**contentBelow**        | Popup content. Renders below the form. | &nbsp;
**contentPartial**      | Popup content partial. Renders above the form. Overrides **content** property. | &nbsp;
**contentPartialBelow** | Popup content partial. Renders below the form. Overrides **contentBelow** property. | &nbsp;
**actionBtnLabel**      | Label of form submit button. | OK
**actionBtnClass**      | Css class of  form submit button. | btn btn-primary
**actionOnClick**       | Action on form submit button. | &nbsp;
**loadIndicator**       | Show loading popup on performing actionOnClick action? | false
**confirm**             | Confirm message for form submit button. | null
**successCallback**     | Javascript callback for execute after success. | null
**form**                | Form config. Must contain fields config. | &nbsp;
**modelClass**          | Model class for form. | \October\Rain\Database\Model

Config for form popup must contain **actionOnClick** and **form** options.

## Overrides

There are methods for the override.

```php
public function getPopupFormModel(string $definition, ?string $modelClass): \October\Rain\Database\Model
{
}
```

```php
public function getPopupContent(string $definition, ?bool $below = false): ?string
{
}
```

```php
public function getPopupTitle(string $definition): ?string
{
}
```

You can use it for override title and content of popups and form model of **form** popups.

Below param of **getPopupContent()** is for overriding the **contentBelow**. 

## Complex example

Below is example of using PopupController for make simple wizard.
For doing this you can use popup id.

```yaml
# vendor/plugin/controllers/mycontroller/step1_popup.yaml

type: form
openBtnLabel: Start wizard
actionBtnLabel: Submit step1
actionOnClick: onSubmitStep1
form:
    fields:
        first_name:
            label: Name
        last_name:
            label: Surname
popupId: wizard
```

```yaml
# vendor/plugin/controllers/mycontroller/step2_popup.yaml

type: form
actionBtnLabel: Submit step2
actionOnClick: onSubmitStep2
inset: true
form:
    fields:
        bio:
            label: Bio
```

```yaml
# vendor/plugin/controllers/mycontroller/result_popup.yaml

type: content
content: Will be overriden in controller
inset: true
```

```php
// vendor/plugin/controllers/MyController.php

use GromIT\PopupBuilder\Behaviors\PopupController;

class MyController extends Controller
{
    public $implement = [
        PopupController::class
    ]; 
    
    public $popupConfig = [
        'step1'        => 'step1_popup.yaml',
        'step2'        => 'step2_popup.yaml',
        'result_popup' => 'result_popup.yaml',
    ];
    
    public function index() {
        
    }
    
    public function onSubmitStep1() {
        // do something with post()
        // #wizard - popupId of step1 popup
        return [
            '#wizard' => $this->popupRender('step2')
        ];
    }
    
    public function onSubmitStep2() {
        // do something with post()
        // #wizard - popupId of step1 popup
        return [
            '#wizard' => $this->popupRender('result_popup')
        ];
    }
    
    public function getPopupContent(string $definition, ?bool $below = false): ?string
    {
        if ($definition === 'result_popup') {
            return '<pre>' . print_r(post(), true) . '</pre>';
        }
        
        return $this->asExtension(PopupController::class)->getPopupContent($definition, $below);
    }
}
```
```php
<!-- vendor/plugin/controllers/mycontroller/index.htm -->
<?= $this->popupRenderOpenBtn('step1') ?>
```