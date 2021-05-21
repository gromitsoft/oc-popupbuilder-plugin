<?php

namespace GromIT\PopupBuilder\Behaviors;

use Backend\Classes\ControllerBehavior;
use Backend\Widgets\Form;
use Illuminate\Support\Facades\Lang;
use October\Rain\Database\Model;
use October\Rain\Exception\SystemException;
use stdClass;

class PopupController extends ControllerBehavior
{
    public const TYPE_CONTENT = 'content';

    public const TYPE_FORM = 'form';

    public const TYPE_MSG = 'msg';

    protected $requiredProperties = ['popupConfig'];

    protected $requiredConfig = ['type'];

    protected $requiredPopupContentConfig = [];

    protected $requiredPopupFormConfig = ['actionOnClick', 'form'];

    protected $requiredPopupMsgConfig = ['content', 'msgType'];

    /**
     * @var stdClass[]
     */
    protected $popupDefinitions;

    /**
     * @var string
     */
    protected $primaryDefinition;

    /**
     * PopupController constructor.
     *
     * @param $controller
     *
     * @throws \October\Rain\Exception\SystemException
     */
    public function __construct($controller)
    {
        parent::__construct($controller);

        $this->viewPath = $this->guessViewPath();

        if (is_array($controller->popupConfig)) {
            $this->popupDefinitions  = $controller->popupConfig;
            $this->primaryDefinition = (string)key($this->popupDefinitions);
        } else {
            $this->popupDefinitions  = ['popup' => $controller->popupConfig];
            $this->primaryDefinition = 'popup';
        }

        $this->makePopupsConfigs();

        $this->bindForms();
    }

    /**
     * @throws \October\Rain\Exception\SystemException
     */
    protected function makePopupsConfigs(): void
    {
        foreach (array_keys($this->popupDefinitions) as $definition) {
            $popupConfig = $this->popupDefinitions[$definition] = $this->makeConfig(
                $this->popupDefinitions[$definition],
                $this->requiredConfig
            );

            switch ($this->popupDefinitions[$definition]->type) {
                case self::TYPE_FORM:
                    $requiredConfig = $this->requiredPopupFormConfig;
                    break;
                case self::TYPE_CONTENT:
                    $requiredConfig = $this->requiredPopupContentConfig;

                    if (!($popupConfig->contentPartial ?? $popupConfig->content ?? null)) {
                        throw new SystemException(Lang::get(
                            'gromit.popupbuilder::lang.config.content_or_content_partial',
                            ['definition' => $definition]
                        ));
                    }

                    break;
                case self::TYPE_MSG:
                    $requiredConfig = $this->requiredPopupMsgConfig;
                    break;
                default:
                    throw new SystemException(Lang::get(
                        'gromit.popupbuilder::lang.config.unknown_popup_type',
                        ['definition' => $definition]
                    ));
            }

            $this->validateConfig($popupConfig, $requiredConfig);
        }
    }

    protected function bindForms(): void
    {
        foreach ($this->popupDefinitions as $definition => $config) {
            if ($config->type === self::TYPE_FORM) {
                $this->bindForm($definition, $config);
            }
        }
    }

    /**
     * @param string          $definition
     * @param \stdClass|array $popupConfig
     *
     * @throws \SystemException
     */
    protected function bindForm(string $definition, $popupConfig): void
    {
        if ($this->controller->methodExists('getPopupFormModel')) {
            /** @noinspection PhpUndefinedMethodInspection */
            $model = $this->controller->getPopupFormModel($definition, $popupConfig->modelClass ?? null);
        } else {
            $model = $this->getPopupFormModel($definition, $popupConfig->modelClass ?? null);
        }

        $formConfig          = $popupConfig->form;
        $formConfig['model'] = $model;
        $formConfig['alias'] = $popupConfig->formAlias ?? $this->makePopupFormAlias($definition);

        $this
            ->controller
            ->makeWidget(Form::class, $formConfig)
            ->bindToController();
    }

    /**
     * @param array|\stdClass $config
     * @param array           $requiredConfig
     *
     * @throws \October\Rain\Exception\SystemException
     */
    protected function validateConfig($config, array $requiredConfig): void
    {
        foreach ($requiredConfig as $property) {
            if (!property_exists($config, $property)) {
                throw new SystemException(Lang::get(
                    'system::lang.config.required',
                    ['property' => $property, 'location' => static::class]
                ));
            }
        }
    }

    /**
     * Render open popup button.
     *
     * @param string|null $definition
     *
     * @return string
     * @throws \October\Rain\Exception\SystemException
     */
    public function popupRenderOpenBtn(?string $definition = null): string
    {
        if ($definition === null) {
            $definition = $this->primaryDefinition;
        }

        $popupConfig = $this->popupDefinitions[$definition];

        $this->validateConfig($popupConfig, [
            'openBtnLabel',
        ]);

        $btnClass = $popupConfig->openBtnClass ?? null;

        if (empty($btnClass)) {
            $btnClass = 'btn btn-default';
        }

        return $this->popupMakePartial('btn', [
            'openBtnClass'    => $btnClass,
            'openBtnLabel'    => $popupConfig->openBtnLabel,
            'popupDefinition' => $definition,
            'popupSize'       => $popupConfig->popupSize ?? 'medium'
        ]);
    }

    /**
     * Open popup AJAX handler.
     *
     * @return string
     * @throws \October\Rain\Exception\SystemException
     */
    public function onOpenPopup(): string
    {
        $definition = post('popupDefinition');

        return $this->popupRender($definition);
    }

    /**
     * Render popup and return.
     *
     * @param string|null $definition
     *
     * @return string
     * @throws \October\Rain\Exception\SystemException
     */
    public function popupRender(?string $definition = null): string
    {
        if ($definition === null || !isset($this->popupDefinitions[$definition])) {
            $definition = $this->primaryDefinition;
        }

        $popupConfig = $this->popupDefinitions[$definition];

        switch ($popupConfig->type) {
            case self::TYPE_CONTENT:
                return $this->renderContentPopup($definition, $popupConfig);
            case self::TYPE_FORM:
                return $this->renderFormPopup($definition, $popupConfig);
            case self::TYPE_MSG:
                return $this->renderMsgPopup($definition, $popupConfig);
            default:
                throw new SystemException(Lang::get(
                    'gromit.popupbuilder::lang.config.unknown_popup_type',
                    ['definition' => $definition]
                ));
        }
    }

    protected function renderContentPopup(string $definition, $popupConfig): string
    {
        $content = $this->controller->getPopupContent($definition);

        $params            = $this->getMainParams($definition, $popupConfig);
        $params['title']   = $popupConfig->title ?? null;
        $params['content'] = $content;

        return $this->popupMakePartial('content_popup', $params);
    }

    protected function renderMsgPopup(string $definition, $popupConfig): string
    {
        $content = $this->controller->getPopupContent($definition);

        $params            = $this->getMainParams($definition, $popupConfig);
        $params['msgType'] = $popupConfig->msgType ?? 'info';
        $params['content'] = $content;

        return $this->popupMakePartial('msg_popup', $params);
    }

    protected function renderFormPopup(string $definition, $popupConfig): string
    {
        $content      = $this->controller->getPopupContent($definition);
        $contentBelow = $this->controller->getPopupContent($definition, true);

        $params                    = $this->getMainParams($definition, $popupConfig);
        $params['title']           = $popupConfig->title ?? null;
        $params['content']         = $content;
        $params['contentBelow']    = $contentBelow;
        $params['actionBtnLabel']  = $popupConfig->actionBtnLabel ?? 'OK';
        $params['actionBtnClass']  = $popupConfig->actionBtnClass ?? 'btn btn-primary';
        $params['loadIndicator']   = $popupConfig->loadIndicator ?? false;
        $params['confirm']         = $popupConfig->confirm ?? null;
        $params['actionOnClick']   = $popupConfig->actionOnClick;
        $params['form']            = $this->controller->widget->{$this->makePopupFormAlias($definition)};
        $params['successCallback'] = $popupConfig->successCallback ?? null;

        return $this->popupMakePartial('form_popup', $params);
    }

    //
    // Helpers
    //

    /**
     * Controller accessor for making partials within this behavior.
     *
     * @param string $partial
     * @param array  $params
     *
     * @return string Partial contents
     */
    public function popupMakePartial(string $partial, array $params = []): string
    {
        $params = array_merge($params, $this->vars);

        $contents = $this->controller->makePartial(
            'popup_' . $partial,
            $params,
            false
        );

        if (!$contents) {
            $contents = $this->makePartial($partial, $params);
        }

        return $contents;
    }

    /**
     * @param string $definition
     *
     * @return string
     */
    protected function makePopupFormAlias(string $definition): string
    {
        return "{$definition}PopupForm";
    }

    private function getModalPadding($popupConfig): string
    {
        $modalBodyPadding = '';

        if ($popupConfig->noPadding ?? false) {
            $modalBodyPadding = 'p-a-0';
        } elseif (empty($popupConfig->title ?? null)) {
            $modalBodyPadding = 'p-t';
        }

        return $modalBodyPadding;
    }

    private function getMainParams(string $definition, $popupConfig): array
    {
        return [
            'closeBtnLabel' => $popupConfig->closeBtnLabel ?? Lang::get('gromit.popupbuilder::lang.close'),
            'closeBtnClass' => $popupConfig->closeBtnClass ?? 'btn btn-default',
            'modalPadding'  => $this->getModalPadding($popupConfig),
            'popupId'       => $popupConfig->popupId ?? $definition,
            'inset'         => $popupConfig->inset ?? false,
        ];
    }

    //
    // Override
    //

    /**
     * @param string      $definition
     * @param string|null $modelClass
     *
     * @return \October\Rain\Database\Model
     * @noinspection PhpUnusedParameterInspection
     */
    public function getPopupFormModel(string $definition, ?string $modelClass): Model
    {
        return $modelClass ? new $modelClass : new Model();
    }

    /**
     * @param string    $definition
     * @param bool|null $below Only for forms
     *
     * @return string|null
     */
    public function getPopupContent(string $definition, ?bool $below = false): ?string
    {
        $popupConfig = $this->popupDefinitions[$definition];

        if ($below) {
            if ($popupConfig->contentPartialBelow ?? null) {
                return $this->controller->makePartial($popupConfig->contentPartialBelow);
            }

            return $popupConfig->contentBelow ?? null;
        }

        if ($popupConfig->contentPartial ?? null) {
            return $this->controller->makePartial($popupConfig->contentPartial);
        }

        return $popupConfig->content ?? null;
    }
}
