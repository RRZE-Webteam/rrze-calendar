<?php

namespace RRZE\Calendar;

defined('ABSPATH') || exit;

use function RRZE\Calendar\Config\getOptionName;
use function RRZE\Calendar\Config\getMenuSettings;
use function RRZE\Calendar\Config\getSections;
use function RRZE\Calendar\Config\getFields;

class Settings
{
    /**
     * Option name.
     * 
     * @var string
     */
    protected static $optionName;

    /**
     * Settings options.
     * 
     * @var array
     */
    protected static $options;

    /**
     * Settings menu
     * 
     * @var array
     */
    protected $settingsMenu;

    /**
     * Settings sections.
     * 
     * @var array
     */
    protected $settingsSections;

    /**
     * All tabs.
     * 
     * @var array
     */
    protected $allTabs = [];

    /**
     * Standard tab.
     * 
     * @var string
     */
    protected $defaultTab = '';

    /**
     * Current tab.
     * 
     * @var string
     */
    protected $currentTab = '';

    /**
     * Settings prefix.
     * @var string
     */
    protected $settingsPrefix;

    /**
     * Hidden sections.
     * @var array
     */
    protected $hiddenSections = [];

    /**
     * Disabled fields.
     * @var array
     */
    protected $hiddenFields = [];

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->settingsPrefix = plugin()->getSlug() . '-';
    }

    /**
     * It runs after the class is instantiated.
     * 
     * @return void
     */
    public function onLoaded()
    {
        self::$optionName = getOptionName();
        self::$options = self::getOptions();

        $this->setMenu();
        $this->setSections();

        add_action('admin_init', [$this, 'adminInit']);
        add_action('admin_menu', [$this, 'adminMenu']);
    }

    protected function setMenu()
    {
        $this->settingsMenu = getmenuSettings();
    }

    /**
     * Set sections.
     */
    protected function setSections()
    {
        $this->settingsSections = getSections();
    }

    /**
     * Returns the option name.
     *
     * @return string
     */
    public static function getOptionName(): string
    {
        return self::$optionName;
    }

    /**
     * Returns the default options.
     * 
     * @return array
     */
    protected static function defaultOptions(): array
    {
        $options = [];

        foreach (getFields() as $section => $field) {
            foreach ($field as $option) {
                $name = $option['name'];
                $default = isset($option['default']) ? $option['default'] : '';
                $options = array_merge($options, [$section . '_' . $name => $default]);
            }
        }

        return $options;
    }

    /**
     * Returns the options.
     * 
     * @return array
     */
    public static function getOptions(): array
    {
        $defaults = self::defaultOptions();

        $options = (array) get_option(self::$optionName);
        $options = wp_parse_args($options, $defaults);
        $options = array_intersect_key($options, $defaults);

        return $options;
    }

    /**
     * Returns an option.
     * 
     * @param string  $name  Settings field name
     * @param string  $section The section name this field belongs to
     * @param string  $default Default text if it's not found
     * @return string
     */
    public function getOption(string $section, string $name, string $default = ''): string
    {
        $option = $section . '_' . $name;

        if (isset(self::$options[$option])) {
            return self::$options[$option];
        }

        return $default;
    }

    /**
     * Sanitize callback for the options.
     * 
     * @return mixed
     */
    public function sanitizeOptions($options)
    {
        if (!$options) {
            return self::$options;
        }

        foreach ($options as $key => $value) {
            self::$options[$key] = $value;
            $sanitizeCallback = $this->getSanitizeCallback($key);
            if ($sanitizeCallback !== false) {
                self::$options[$key] = call_user_func($sanitizeCallback, $value);
            }
        }

        if (self::$options['endpoint_enabled'] == 'on') {
            Endpoint::addEndpoint();
        }
        flush_rewrite_rules(false);

        return self::$options;
    }

    /**
     * Returns a sanitized option for the specified option key.
     * 
     * @param string $key Option key
     * @return mixed string|boolean false
     */
    protected function getSanitizeCallback(string $key = '')
    {
        if (empty($key)) {
            return false;
        }

        foreach (getFields() as $section => $options) {
            foreach ($options as $option) {
                if ($section . '_' . $option['name'] != $key) {
                    continue;
                }

                return isset($option['sanitize_callback']) && is_callable($option['sanitize_callback']) ? $option['sanitize_callback'] : false;
            }
        }

        return false;
    }

    /**
     * Display the settings sections as tabs.
     * Displays all labels of the setting sections as a tab.
     */
    public function showTabs()
    {
        $html = '<h1>' . $this->settingsMenu['title'] . '</h1>' . PHP_EOL;

        if (count($this->settingsSections) - count($this->hiddenSections) < 2) {
            echo $html;
            return;
        }

        $html .= '<h2 class="nav-tab-wrapper wp-clearfix">';

        foreach ($this->settingsSections as $section) {
            if (in_array($section['id'], $this->hiddenSections)) {
                continue;
            }
            $class = $this->settingsPrefix . $section['id'] == $this->currentTab ? 'nav-tab-active' : $this->defaultTab;
            $html .= sprintf(
                '<a href="?page=%4$s&current-tab=%1$s" class="nav-tab %3$s" id="%1$s-tab">%2$s</a>',
                esc_attr($this->settingsPrefix . $section['id']),
                $section['title'],
                esc_attr($class),
                $this->settingsMenu['menu_slug']
            );
        }

        $html .= '</h2>' . PHP_EOL;

        echo $html;
    }

    /**
     * Display the setting sections.
     * Displays the corresponding form for each setting sections.
     */
    public function showSections()
    {
        foreach ($this->settingsSections as $section) {
            if ($this->settingsPrefix . $section['id'] != $this->currentTab) {
                continue;
            }
?>
            <div id="<?php echo $this->settingsPrefix . $section['id']; ?>">
                <form method="post" action="options.php">
                    <?php settings_fields($this->settingsPrefix . $section['id']); ?>
                    <?php do_settings_sections($this->settingsPrefix . $section['id']); ?>
                    <?php submit_button(); ?>
                </form>
            </div>
<?php
        }
    }

    /**
     * Settings page output.
     */
    public function pageOutput()
    {
        echo '<div class="wrap">', PHP_EOL;
        $this->showTabs();
        $this->showSections();
        echo '</div>', PHP_EOL;
    }

    /**
     * Registration of sections and fields.
     */
    public function adminInit()
    {
        // Add hidden sections
        foreach ($this->settingsSections as $section) {
            $hide = (bool) apply_filters('rrze_calendar_hide_section_' . $section['id'], false);
            if ($hide) {
                $this->hiddenSections[] = $section['id'];
            }
        }

        // Add setting sections
        foreach ($this->settingsSections as $section) {
            if (!empty($section['desc'])) {
                $section['desc'] = '<div class="inside">' . $section['desc'] . '</div>';
                $callback = function () use ($section) {
                    echo $section['desc'];
                };
            } elseif (isset($section['callback'])) {
                $callback = $section['callback'];
            } else {
                $callback = null;
            }

            add_settings_section($this->settingsPrefix . $section['id'], $section['title'], $callback, $this->settingsPrefix . $section['id']);
        }

        $fields = getFields();
        // Add hidden fields
        foreach ($fields as $section => $field) {
            foreach ($field as $option) {
                $hide = (bool) apply_filters('rrze_calendar_hide_field_' . $section . '_' . $option['name'], false);
                if ($hide) {
                    $this->hiddenFields[] = $section . '_' . $option['name'];
                }
            }
        }

        // Add setting fields
        foreach ($fields as $section => $field) {
            if (in_array($section, $this->hiddenSections)) {
                continue;
            }
            foreach ($field as $option) {
                if (in_array($section . '_' . $option['name'], $this->hiddenFields)) {
                    continue;
                }
                $name = $option['name'];
                $type = isset($option['type']) ? $option['type'] : 'text';
                $label = isset($option['label']) ? $option['label'] : '';
                $callback = isset($option['callback']) ? $option['callback'] : [$this, 'callback' . ucfirst($type)];

                $args = [
                    'id' => $name,
                    'class' => isset($option['class']) ? $option['class'] : $name,
                    'label_for' => "{$section}[{$name}]",
                    'desc' => isset($option['desc']) ? $option['desc'] : '',
                    'name' => $label,
                    'section' => $section,
                    'size' => isset($option['size']) ? $option['size'] : null,
                    'options' => isset($option['options']) ? $option['options'] : '',
                    'default' => isset($option['default']) ? $option['default'] : '',
                    'sanitize_callback' => isset($option['sanitize_callback']) ? $option['sanitize_callback'] : '',
                    'type' => $type,
                    'placeholder' => isset($option['placeholder']) ? $option['placeholder'] : '',
                    'min' => isset($option['min']) ? $option['min'] : '',
                    'max' => isset($option['max']) ? $option['max'] : '',
                    'step' => isset($option['step']) ? $option['step'] : '',
                    'disabled' => isset($option['disabled']) ? 'disabled' : ''
                ];

                add_settings_field("{$section}[{$name}]", $label, $callback, $this->settingsPrefix . $section, $this->settingsPrefix . $section, $args);
            }
        }

        // Registrieren der Einstellungen
        foreach ($this->settingsSections as $section) {
            register_setting($this->settingsPrefix . $section['id'], self::$optionName, [$this, 'sanitizeOptions']);
        }
    }

    /**
     * Add the options page.
     * @return void
     */
    public function adminMenu()
    {
        $this->setTabs();

        add_options_page(
            $this->settingsMenu['page_title'],
            $this->settingsMenu['menu_title'],
            $this->settingsMenu['capability'],
            $this->settingsMenu['menu_slug'],
            [$this, 'pageOutput']
        );
    }

    /**
     * Set tabs.
     */
    protected function setTabs()
    {
        foreach ($this->settingsSections as $key => $val) {
            if ($key == 0) {
                $this->defaultTab = $this->settingsPrefix . $val['id'];
            }
            $this->allTabs[] = $this->settingsPrefix . $val['id'];
        }

        $this->currentTab = array_key_exists('current-tab', $_GET) && in_array($_GET['current-tab'], $this->allTabs) ? $_GET['current-tab'] : $this->defaultTab;
    }

    /**
     * Returns a description of the settings field.
     * @param array   $args Description arguments
     */
    public function getFieldDescription(array $args)
    {
        if (!empty($args['desc'])) {
            $desc = sprintf('<p class="description">%s</p>', $args['desc']);
        } else {
            $desc = '';
        }

        return $desc;
    }

    /**
     * Displays a text input field.
     * @param array   $args Field settings arguments
     */
    public function callbackText(array $args)
    {
        $value = esc_attr($this->getOption($args['section'], $args['id'], $args['default']));
        $size = isset($args['size']) && !is_null($args['size']) ? $args['size'] : 'regular';
        $type = isset($args['type']) ? $args['type'] : 'text';
        $placeholder = empty($args['placeholder']) ? '' : ' placeholder="' . $args['placeholder'] . '"';

        $html = sprintf(
            '<input type="%1$s" class="%2$s-text" id="%4$s-%5$s" name="%3$s[%4$s_%5$s]" value="%6$s"%7$s>',
            $type,
            $size,
            self::$optionName,
            $args['section'],
            $args['id'],
            $value,
            $placeholder
        );
        $html .= $this->getFieldDescription($args);

        echo $html;
    }

    /**
     * Displays a number input field.
     * @param array   $args Field settings arguments
     */
    public function callbackNumber(array $args)
    {
        $value = esc_attr($this->getOption($args['section'], $args['id'], $args['default']));
        $size = isset($args['size']) && !is_null($args['size']) ? $args['size'] : 'regular';
        $type = isset($args['type']) ? $args['type'] : 'number';
        $placeholder = empty($args['placeholder']) ? '' : ' placeholder="' . $args['placeholder'] . '"';
        $min = ($args['min'] == '') ? '' : ' min="' . $args['min'] . '"';
        $max = ($args['max'] == '') ? '' : ' max="' . $args['max'] . '"';
        $step = ($args['step'] == '') ? '' : ' step="' . $args['step'] . '"';

        $html = sprintf(
            '<input type="%1$s" class="%2$s-number" id="%4$s-%5$s" name="%3$s[%4$s_%5$s]" value="%6$s"%7$s%8$s%9$s%10$s>',
            $type,
            $size,
            self::$optionName,
            $args['section'],
            $args['id'],
            $value,
            $placeholder,
            $min,
            $max,
            $step
        );
        $html .= $this->getFieldDescription($args);

        echo $html;
    }

    /**
     * Displays a checkbox input field.
     * @param array   $args Field settings arguments
     */
    public function callbackCheckbox(array $args)
    {
        $value = esc_attr($this->getOption($args['section'], $args['id'], $args['default']));

        $html = '<fieldset>';
        $html .= sprintf(
            '<label for="%1$s-%2$s">',
            $args['section'],
            $args['id']
        );
        $html .= sprintf(
            '<input type="hidden" name="%1$s[%2$s_%3$s]" value="off">',
            self::$optionName,
            $args['section'],
            $args['id']
        );
        $html .= sprintf(
            '<input type="checkbox" class="checkbox" id="%2$s-%3$s" name="%1$s[%2$s_%3$s]" value="on" %4$s>',
            self::$optionName,
            $args['section'],
            $args['id'],
            checked($value, 'on', false)
        );
        $html .= sprintf(
            '%1$s</label>',
            $args['desc']
        );
        $html .= '</fieldset>';

        echo $html;
    }

    /**
     * Displays a multi-checkbox input field.
     * @param array   $args Field settings arguments
     */
    public function callbackMulticheck(array $args)
    {
        $value = $this->getOption($args['section'], $args['id'], $args['default']);
        $html = '<fieldset>';
        $html .= sprintf(
            '<input type="hidden" name="%1$s[%2$s_%3$s]" value="">',
            self::$optionName,
            $args['section'],
            $args['id']
        );
        foreach ($args['options'] as $key => $label) {
            $checked = isset($value[$key]) ? $value[$key] : '0';
            $html .= sprintf(
                '<label for="%1$s-%2$s-%3$s">',
                $args['section'],
                $args['id'],
                $key
            );
            $html .= sprintf(
                '<input type="checkbox" class="checkbox" id="%2$s-%3$s-%4$s" name="%1$s[%2$s_%3$s][%4$s]" value="%4$s" %5$s>',
                self::$optionName,
                $args['section'],
                $args['id'],
                $key,
                checked($checked, $key, false)
            );
            $html .= sprintf('%1$s</label><br>', $label);
        }

        $html .= $this->getFieldDescription($args);
        $html .= '</fieldset>';

        echo $html;
    }

    /**
     * Displays a radio input field.
     * @param array   $args Field settings arguments
     */
    public function callbackRadio(array $args)
    {
        $value = $this->getOption($args['section'], $args['id'], $args['default']);
        $html  = '<fieldset>';

        foreach ($args['options'] as $key => $label) {
            $html .= sprintf(
                '<label for="%1$s-%2$s-%3$s">',
                $args['section'],
                $args['id'],
                $key
            );
            $html .= sprintf(
                '<input type="radio" class="radio" id="%2$s-%3$s-%4$s" name="%1$s[%2$s_%3$s]" value="%4$s" %5$s>',
                self::$optionName,
                $args['section'],
                $args['id'],
                $key,
                checked($value, $key, false)
            );
            $html .= sprintf(
                '%1$s</label><br>',
                $label
            );
        }

        $html .= $this->getFieldDescription($args);
        $html .= '</fieldset>';

        echo $html;
    }

    /**
     * Displays a selectbox field.
     * @param array   $args Field settings arguments
     */
    public function callbackSelect(array $args)
    {
        $value = esc_attr($this->getOption($args['section'], $args['id'], $args['default']));
        $class  = isset($args['class']) && !is_null($args['class']) ? $args['class'] : '';
        $html  = sprintf(
            '<select class="%1$s" id="%3$s-%4$s" name="%2$s[%3$s_%4$s]">',
            $class,
            self::$optionName,
            $args['section'],
            $args['id']
        );

        foreach ($args['options'] as $key => $label) {
            $html .= sprintf(
                '<option value="%s"%s>%s</option>',
                $key,
                selected($value, $key, false),
                $label
            );
        }

        $html .= sprintf('</select>');
        $html .= $this->getFieldDescription($args);

        echo $html;
    }

    /**
     * Displays a multi-selectbox field.
     * @param array   $args Field settings arguments
     */
    public function callbackMultiSelect(array $args)
    {
        $value = (array) $this->getOption($args['section'], $args['id'], $args['default']);
        $size  = isset($args['size']) && !is_null($args['size']) ? $args['size'] : 'regular';
        $html  = sprintf(
            '<select class="%1$s" id="%3$s-%4$s" name="%2$s[%3$s_%4$s][]" multiple="multiple">',
            $size,
            self::$optionName,
            $args['section'],
            $args['id']
        );

        foreach ($args['options'] as $key => $label) {
            $html .= sprintf(
                '<option value="%s"%s>%s</option>',
                $key,
                selected(true, in_array($key, $value), false),
                $label
            );
        }

        $html .= sprintf('</select>');
        $html .= $this->getFieldDescription($args);

        echo $html;
    }


    /**
     * Displays a textarea field.
     * @param array   $args Field settings arguments
     */
    public function callbackTextarea(array $args)
    {
        $value = esc_textarea($this->getOption($args['section'], $args['id'], $args['default']));
        $size = isset($args['size']) && !is_null($args['size']) ? $args['size'] : 'regular';
        $placeholder = empty($args['placeholder']) ? '' : ' placeholder="' . $args['placeholder'] . '"';

        $html = sprintf(
            '<textarea rows="10" cols="55" class="%1$s-text" id="%3$s-%4$s" name="%2$s[%3$s_%4$s]"%5$s>%6$s</textarea>',
            $size,
            self::$optionName,
            $args['section'],
            $args['id'],
            $placeholder,
            $value
        );
        $html .= $this->getFieldDescription($args);

        echo $html;
    }
}
