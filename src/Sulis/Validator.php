<?php

declare(strict_types=1);

namespace Sulis;

use DateTime;
use InvalidArgumentException;

class Validator
{
    protected $fields = [];
    protected $errors = [];
    protected $validations = [];
    protected $labels = [];
    protected $instanceRules = [];
    protected $instanceRuleMessage = [];

    protected static $lang;
    protected static $langDir;
    protected static $rules = [];
    protected static $ruleMessages = [];

    protected $validUrlPrefixes = ['http://', 'https://', 'ftp://'];
    protected $bail = false;
    protected $prependLabels = true;

    public function __construct($data = [], $fields = [], $lang = null, $langDir = null)
    {
        $this->fields = empty($fields) ? $data : array_intersect_key($data, array_flip($fields));
        $lang = $lang ?: static::lang();
        $langDir = $langDir ?: static::langDir();
        $langFile = rtrim($langDir, '/') . '/' . $lang . '.php';

        if (stream_resolve_include_path($langFile)) {
            $langMessages = include $langFile;
            static::$ruleMessages = array_merge(static::$ruleMessages, $langMessages);
        } else {
            throw new InvalidArgumentException("Fail to load language file '" . $langFile . "'");
        }
    }

    public function make($data = [], $fields = [], $lang = null, $langDir = null)
    {
        $this->__construct($data, $fields, $lang, $langDir);
        return $this;
    }

    public static function lang($lang = null)
    {
        if ($lang !== null) {
            static::$lang = $lang;
        }

        return static::$lang ?: 'en';
    }

    public static function langDir($dir = null)
    {
        if ($dir !== null) {
            static::$langDir = $dir;
        }

        return static::$langDir ?: dirname(__DIR__) . '/lang/validator';
    }

    public function setPrependLabels($prependLabels = true)
    {
        $this->prependLabels = $prependLabels;
    }

    protected function validateRequired($field, $value, $params = [])
    {
        if (isset($params[0]) && (bool)$params[0]) {
            $find = $this->getPart($this->fields, explode('.', $field), true);
            return $find[1];
        }

        if (is_null($value) || (is_string($value) && trim($value) === '')) {
            return false;
        }

        return true;
    }

    protected function validateEquals($field, $value, array $params)
    {
        list($field2Value, $multiple) = $this->getPart($this->fields, explode('.', $params[0]));
        return isset($field2Value) && $value == $field2Value;
    }

    protected function validateDifferent($field, $value, array $params)
    {
        list($field2Value, $multiple) = $this->getPart($this->fields, explode('.', $params[0]));
        return isset($field2Value) && $value != $field2Value;
    }

    protected function validateAccepted($field, $value)
    {
        $acceptable = ['yes', 'on', 1, '1', true];
        return $this->validateRequired($field, $value) && in_array($value, $acceptable, true);
    }

    protected function validateArray($field, $value)
    {
        return is_array($value);
    }

    protected function validateNumeric($field, $value)
    {
        return is_numeric($value);
    }

    protected function validateInteger($field, $value, $params)
    {
        if (isset($params[0]) && (bool) $params[0]) {
            return preg_match('/^([0-9]|-[1-9]|-?[1-9][0-9]*)$/i', $value);
        }

        return filter_var($value, FILTER_VALIDATE_INT) !== false;
    }

    protected function validateLength($field, $value, $params)
    {
        $length = $this->stringLength($value);

        if (isset($params[1])) {
            return $length >= $params[0] && $length <= $params[1];
        }

        return ($length !== false) && $length == $params[0];
    }

    protected function validateLengthBetween($field, $value, $params)
    {
        $length = $this->stringLength($value);
        return ($length !== false) && $length >= $params[0] && $length <= $params[1];
    }

    protected function validateLengthMin($field, $value, $params)
    {
        $length = $this->stringLength($value);
        return ($length !== false) && $length >= $params[0];
    }

    protected function validateLengthMax($field, $value, $params)
    {
        $length = $this->stringLength($value);
        return ($length !== false) && $length <= $params[0];
    }

    protected function stringLength($value)
    {
        if (! is_string($value)) {
            return false;
        } elseif (function_exists('mb_strlen')) {
            return mb_strlen($value);
        }

        return strlen($value);
    }


    protected function validateMin($field, $value, $params)
    {
        if (! is_numeric($value)) {
            return false;
        } elseif (function_exists('bccomp')) {
            return ! (bccomp($params[0], $value, 14) === 1);
        } else {
            return $params[0] <= $value;
        }
    }


    protected function validateMax($field, $value, $params)
    {
        if (! is_numeric($value)) {
            return false;
        } elseif (function_exists('bccomp')) {
            return ! (bccomp($value, $params[0], 14) === 1);
        } else {
            return $params[0] >= $value;
        }
    }


    protected function validateBetween($field, $value, $params)
    {
        if (! is_numeric($value)) {
            return false;
        }

        if (! isset($params[0]) || ! is_array($params[0]) || count($params[0]) !== 2) {
            return false;
        }

        list($min, $max) = $params[0];
        return $this->validateMin($field, $value, [$min]) && $this->validateMax($field, $value, [$max]);
    }


    protected function validateIn($field, $value, $params)
    {
        $forceAsAssociative = false;

        if (isset($params[2])) {
            $forceAsAssociative = (bool) $params[2];
        }

        if ($forceAsAssociative || $this->isAssociativeArray($params[0])) {
            $params[0] = array_keys($params[0]);
        }

        $strict = false;

        if (isset($params[1])) {
            $strict = $params[1];
        }

        return in_array($value, $params[0], $strict);
    }


    protected function validateListContains($field, $value, $params)
    {
        $forceAsAssociative = false;

        if (isset($params[2])) {
            $forceAsAssociative = (bool) $params[2];
        }

        if ($forceAsAssociative || $this->isAssociativeArray($value)) {
            $value = array_keys($value);
        }

        $strict = false;

        if (isset($params[1])) {
            $strict = $params[1];
        }

        return in_array($params[0], $value, $strict);
    }


    protected function validateNotIn($field, $value, $params)
    {
        return ! $this->validateIn($field, $value, $params);
    }


    protected function validateContains($field, $value, $params)
    {
        if (! isset($params[0])) {
            return false;
        }

        if (! is_string($params[0]) || ! is_string($value)) {
            return false;
        }

        $strict = true;

        if (isset($params[1])) {
            $strict = (bool)$params[1];
        }

        if ($strict) {
            if (function_exists('mb_strpos')) {
                $isContains = mb_strpos($value, $params[0]) !== false;
            } else {
                $isContains = strpos($value, $params[0]) !== false;
            }
        } else {
            if (function_exists('mb_stripos')) {
                $isContains = mb_stripos($value, $params[0]) !== false;
            } else {
                $isContains = stripos($value, $params[0]) !== false;
            }
        }
        return $isContains;
    }


    protected function validateSubset($field, $value, $params)
    {
        if (! isset($params[0])) {
            return false;
        }

        if (! is_array($params[0])) {
            $params[0] = [$params[0]];
        }

        if (is_scalar($value) || is_null($value)) {
            return $this->validateIn($field, $value, $params);
        }

        $intersect = array_intersect($value, $params[0]);
        return array_diff($value, $intersect) === array_diff($intersect, $value);
    }


    protected function validateContainsUnique($field, $value)
    {
        if (! is_array($value)) {
            return false;
        }

        return $value === array_unique($value, SORT_REGULAR);
    }


    protected function validateIp($field, $value)
    {
        return filter_var($value, FILTER_VALIDATE_IP) !== false;
    }


    protected function validateIpv4($field, $value)
    {
        return filter_var($value, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false;
    }


    protected function validateIpv6($field, $value)
    {
        return filter_var($value, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false;
    }


    protected function validateEmail($field, $value)
    {
        return filter_var($value, FILTER_VALIDATE_EMAIL) !== false;
    }


    protected function validateAscii($field, $value)
    {
        if (function_exists('mb_detect_encoding')) {
            return mb_detect_encoding($value, 'ASCII', true);
        }

        return 0 === preg_match('/[^\x00-\x7F]/', $value);
    }


    protected function validateEmailDNS($field, $value)
    {
        if ($this->validateEmail($field, $value)) {
            $domain = ltrim(stristr($value, '@'), '@') . '.';

            if (function_exists('idn_to_ascii') && defined('INTL_IDNA_VARIANT_UTS46')) {
                $domain = idn_to_ascii($domain, 0, INTL_IDNA_VARIANT_UTS46);
            }

            return checkdnsrr($domain, 'MX');
        }

        return false;
    }


    protected function validateUrl($field, $value)
    {
        foreach ($this->validUrlPrefixes as $prefix) {
            if (strpos($value, $prefix) !== false) {
                return filter_var($value, FILTER_VALIDATE_URL) !== false;
            }
        }

        return false;
    }


    protected function validateUrlActive($field, $value)
    {
        foreach ($this->validUrlPrefixes as $prefix) {
            if (strpos($value, $prefix) !== false) {
                $host = parse_url(strtolower($value), PHP_URL_HOST);
                return checkdnsrr($host, 'A') || checkdnsrr($host, 'AAAA') || checkdnsrr($host, 'CNAME');
            }
        }

        return false;
    }


    protected function validateAlpha($field, $value)
    {
        return preg_match('/^([a-z])+$/i', (string) $value);
    }


    protected function validateAlphaNum($field, $value)
    {
        return preg_match('/^([a-z0-9])+$/i', (string) $value);
    }


    protected function validateSlug($field, $value)
    {
        if (is_array($value)) {
            return false;
        }

        return preg_match('/^([-a-z0-9_-])+$/i', (string) $value);
    }


    protected function validateRegex($field, $value, $params)
    {
        return preg_match($params[0], (string) $value);
    }


    protected function validateDate($field, $value)
    {
        $isDate = false;

        if ($value instanceof DateTime) {
            $isDate = true;
        } else {
            $isDate = strtotime($value) !== false;
        }

        return $isDate;
    }


    protected function validateDateFormat($field, $value, $params)
    {
        $parsed = date_parse_from_format($params[0], $value);
        return $parsed['error_count'] === 0 && $parsed['warning_count'] === 0;
    }


    protected function validateDateBefore($field, $value, $params)
    {
        $vtime = ($value instanceof DateTime) ? $value->getTimestamp() : strtotime($value);
        $ptime = ($params[0] instanceof DateTime) ? $params[0]->getTimestamp() : strtotime($params[0]);

        return $vtime < $ptime;
    }


    protected function validateDateAfter($field, $value, $params)
    {
        $vtime = ($value instanceof DateTime) ? $value->getTimestamp() : strtotime($value);
        $ptime = ($params[0] instanceof DateTime) ? $params[0]->getTimestamp() : strtotime($params[0]);

        return $vtime > $ptime;
    }


    protected function validateBoolean($field, $value)
    {
        return is_bool($value);
    }


    protected function validateCreditCard($field, $value, $params)
    {
        if (! empty($params)) {
            if (is_array($params[0])) {
                $cards = $params[0];
            } elseif (is_string($params[0])) {
                $cardType = $params[0];

                if (isset($params[1]) && is_array($params[1])) {
                    $cards = $params[1];

                    if (! in_array($cardType, $cards)) {
                        return false;
                    }
                }
            }
        }

        $numberIsValid = function () use ($value) {
            $number = preg_replace('/[^0-9]+/', '', $value);
            $sum = 0;

            $strlen = strlen($number);

            if ($strlen < 13) {
                return false;
            }

            for ($i = 0; $i < $strlen; $i++) {
                $digit = (int)substr($number, $strlen - $i - 1, 1);

                if ($i % 2 == 1) {
                    $sub_total = $digit * 2;

                    if ($sub_total > 9) {
                        $sub_total = ($sub_total - 10) + 1;
                    }
                } else {
                    $sub_total = $digit;
                }

                $sum += $sub_total;
            }

            if ($sum > 0 && $sum % 10 == 0) {
                return true;
            }

            return false;
        };

        if ($numberIsValid()) {
            if (! isset($cards)) {
                return true;
            } else {
                $cardRegex = [
                    'visa' => '#^4[0-9]{12}(?:[0-9]{3})?$#',
                    'mastercard' => '#^(5[1-5]|2[2-7])[0-9]{14}$#',
                    'amex' => '#^3[47][0-9]{13}$#',
                    'dinersclub' => '#^3(?:0[0-5]|[68][0-9])[0-9]{11}$#',
                    'discover' => '#^6(?:011|5[0-9]{2})[0-9]{12}$#',
                ];

                if (isset($cardType)) {
                    if (! isset($cards) && ! in_array($cardType, array_keys($cardRegex))) {
                        return false;
                    }

                    return (preg_match($cardRegex[$cardType], $value) === 1);
                } elseif (isset($cards)) {
                    foreach ($cards as $card) {
                        if (in_array($card, array_keys($cardRegex)) && preg_match($cardRegex[$card], $value) === 1) {
                            return true;
                        }
                    }
                } else {
                    foreach ($cardRegex as $regex) {
                        if (preg_match($regex, $value) === 1) {
                            return true;
                        }
                    }
                }
            }
        }

        return false;
    }

    protected function validateInstanceOf($field, $value, $params)
    {
        $isInstanceOf = false;

        if (is_object($value)) {
            if (is_object($params[0]) && $value instanceof $params[0]) {
                $isInstanceOf = true;
            }

            if (get_class($value) === $params[0]) {
                $isInstanceOf = true;
            }
        }

        if (is_string($value)) {
            if (is_string($params[0]) && get_class($value) === $params[0]) {
                $isInstanceOf = true;
            }
        }

        return $isInstanceOf;
    }


    protected function validateRequiredWith($field, $value, $params, $fields)
    {
        $conditionallyReq = false;

        if (isset($params[0])) {
            $reqParams = is_array($params[0]) ? $params[0] : [$params[0]];
            $allRequired = isset($params[1]) && (bool)$params[1];
            $emptyFields = 0;

            foreach ($reqParams as $requiredField) {
                if (isset($fields[$requiredField])
                && ! is_null($fields[$requiredField])
                && (is_string($fields[$requiredField]) ? trim($fields[$requiredField]) !== '' : true)) {
                    if (! $allRequired) {
                        $conditionallyReq = true;
                        break;
                    } else {
                        $emptyFields++;
                    }
                }
            }

            if ($allRequired && $emptyFields === count($reqParams)) {
                $conditionallyReq = true;
            }
        }

        if ($conditionallyReq && (is_null($value) || is_string($value) && trim($value) === '')) {
            return false;
        }

        return true;
    }


    protected function validateRequiredWithout($field, $value, $params, $fields)
    {
        $conditionallyReq = false;

        if (isset($params[0])) {
            $reqParams = is_array($params[0]) ? $params[0] : [$params[0]];
            $allEmpty = isset($params[1]) && (bool)$params[1];
            $filledFields = 0;

            foreach ($reqParams as $requiredField) {
                if (! isset($fields[$requiredField])
                || (is_null($fields[$requiredField])
                || (is_string($fields[$requiredField]) && trim($fields[$requiredField]) === ''))) {
                    if (! $allEmpty) {
                        $conditionallyReq = true;
                        break;
                    } else {
                        $filledFields++;
                    }
                }
            }

            if ($allEmpty && $filledFields === count($reqParams)) {
                $conditionallyReq = true;
            }
        }

        if ($conditionallyReq && (is_null($value) || is_string($value) && trim($value) === '')) {
            return false;
        }

        return true;
    }


    protected function validateOptional($field, $value, $params)
    {
        return true;
    }

    protected function validateArrayHasKeys($field, $value, $params)
    {
        if (! is_array($value) || ! isset($params[0])) {
            return false;
        }

        $requiredFields = $params[0];

        if (count($requiredFields) === 0) {
            return false;
        }

        foreach ($requiredFields as $fieldName) {
            if (! array_key_exists($fieldName, $value)) {
                return false;
            }
        }
        return true;
    }


    public function data()
    {
        return $this->fields;
    }


    public function errors($field = null)
    {
        if ($field !== null) {
            return isset($this->errors[$field]) ? $this->errors[$field] : false;
        }

        return $this->errors;
    }


    public function error($field, $message, array $params = [])
    {
        $message = $this->checkAndSetLabel($field, $message, $params);
        $values = [];

        foreach ($params as $param) {
            if (is_array($param)) {
                $param = "['" . implode("', '", $param) . "']";
            }

            if ($param instanceof DateTime) {
                $param = $param->format('Y-m-d');
            } else {
                if (is_object($param)) {
                    $param = get_class($param);
                }
            }

            if (is_string($params[0]) && isset($this->labels[$param])) {
                $param = $this->labels[$param];
            }
            $values[] = $param;
        }

        $this->errors[$field][] = vsprintf($message, $values);
    }


    public function message($message)
    {
        $this->validations[count($this->validations) - 1]['message'] = $message;
        return $this;
    }


    public function reset()
    {
        $this->fields = [];
        $this->errors = [];
        $this->validations = [];
        $this->labels = [];
    }

    protected function getPart($data, $identifiers, $allow_empty = false)
    {
        if (is_array($identifiers) && count($identifiers) === 0) {
            return [$data, false];
        }

        if (is_scalar($data)) {
            return [null, false];
        }

        $identifier = array_shift($identifiers);

        if ($identifier === '*') {
            $values = [];

            foreach ($data as $row) {
                list($value, $multiple) = $this->getPart($row, $identifiers, $allow_empty);

                if ($multiple) {
                    $values = array_merge($values, $value);
                } else {
                    $values[] = $value;
                }
            }

            return [$values, true];
        } elseif ($identifier === null || ! isset($data[$identifier])) {
            if ($allow_empty) {
                return [null, array_key_exists($identifier, $data)];
            }

            return [null, false];
        } elseif (count($identifiers) === 0) {
            if ($allow_empty) {
                return [null, array_key_exists($identifier, $data)];
            }

            return [$data[$identifier], $allow_empty];
        } else {
            return $this->getPart($data[$identifier], $identifiers, $allow_empty);
        }
    }

    private function validationMustBeExcecuted($validation, $field, $values, $multiple)
    {
        if (in_array($validation['rule'], ['requiredWith', 'requiredWithout'])) {
            return true;
        }

        if ($this->hasRule('optional', $field) && ! isset($values)) {
            return false;
        }

        if (!  $this->hasRule('required', $field) && ! in_array($validation['rule'], ['required', 'accepted'])) {
            if ($multiple) {
                return count($values) != 0;
            }

            return (isset($values) && $values !== '');
        }

        return true;
    }

    public function validate()
    {
        $set_to_break = false;

        foreach ($this->validations as $v) {
            foreach ($v['fields'] as $field) {
                list($values, $multiple) = $this->getPart($this->fields, explode('.', $field), false);

                if (!  $this->validationMustBeExcecuted($v, $field, $values, $multiple)) {
                    continue;
                }

                $errors = $this->getRules();

                if (isset($errors[$v['rule']])) {
                    $callback = $errors[$v['rule']];
                } else {
                    $callback = [$this, 'validate' . ucfirst($v['rule'])];
                }

                if (! $multiple) {
                    $values = [$values];
                } elseif (!  $this->hasRule('required', $field)) {
                    $values = array_filter($values);
                }

                $result = true;

                foreach ($values as $value) {
                    $result = $result && call_user_func($callback, $field, $value, $v['params'], $this->fields);
                }

                if (! $result) {
                    $this->error($field, $v['message'], $v['params']);

                    if ($this->bail) {
                        $set_to_break = true;
                        break;
                    }
                }
            }
            if ($set_to_break) {
                break;
            }
        }

        return count($this->errors()) === 0;
    }


    public function stopOnFirstFail($stop = true)
    {
        $this->bail = (bool) $stop;
    }


    protected function getRules()
    {
        return array_merge($this->instanceRules, static::$rules);
    }


    protected function getRuleMessages()
    {
        return array_merge($this->instanceRuleMessage, static::$ruleMessages);
    }


    protected function hasRule($name, $field)
    {
        foreach ($this->validations as $validation) {
            if ($validation['rule'] == $name && in_array($field, $validation['fields'])) {
                return true;
            }
        }

        return false;
    }

    protected static function assertRuleCallback($callback)
    {
        if (! is_callable($callback)) {
            throw new InvalidArgumentException(
                'Second argument must be a valid callback. Given argument was not callable.'
            );
        }
    }


    public function addInstanceRule($name, $callback, $message = null)
    {
        static::assertRuleCallback($callback);

        $this->instanceRules[$name] = $callback;
        $this->instanceRuleMessage[$name] = $message;
    }


    public static function addRule($name, $callback, $message = null)
    {
        if ($message === null) {
            $message = 'Invalid';
        }

        static::assertRuleCallback($callback);

        static::$rules[$name] = $callback;
        static::$ruleMessages[$name] = $message;
    }


    public function getUniqueRuleName($fields)
    {
        if (is_array($fields)) {
            $fields = implode("_", $fields);
        }

        $orgName = "{$fields}_rule";
        $name = $orgName;
        $rules = $this->getRules();
        while (isset($rules[$name])) {
            $name = $orgName . "_" . rand(0, 10000);
        }

        return $name;
    }


    public function hasValidator($name)
    {
        $rules = $this->getRules();
        return method_exists($this, "validate" . ucfirst($name))
            || isset($rules[$name]);
    }


    public function rule($rule, $fields)
    {
        $params = array_slice(func_get_args(), 2);

        if (is_callable($rule)
            && !(is_string($rule) && $this->hasValidator($rule))) {
            $name = $this->getUniqueRuleName($fields);
            $message = isset($params[0]) ? $params[0] : null;
            $this->addInstanceRule($name, $rule, $message);
            $rule = $name;
        }

        $errors = $this->getRules();
        if (! isset($errors[$rule])) {
            $ruleMethod = 'validate' . ucfirst($rule);
            if (! method_exists($this, $ruleMethod)) {
                throw new InvalidArgumentException(
                    "Rule '" . $rule . "' has not been registered with " . get_called_class() . "::addRule()."
                );
            }
        }

        $messages = $this->getRuleMessages();
        $message = isset($messages[$rule]) ? $messages[$rule] : 'Invalid';

        if (function_exists('mb_strpos')) {
            $notContains = mb_strpos($message, '{field}') === false;
        } else {
            $notContains = strpos($message, '{field}') === false;
        }

        if ($notContains) {
            $message = '{field} ' . $message;
        }

        $this->validations[] = [
            'rule' => $rule,
            'fields' => (array) $fields,
            'params' => (array) $params,
            'message' => $message,
        ];

        return $this;
    }


    public function label($value)
    {
        $lastRules = $this->validations[count($this->validations) - 1]['fields'];
        $this->labels([$lastRules[0] => $value]);

        return $this;
    }


    public function labels($labels = [])
    {
        $this->labels = array_merge($this->labels, $labels);
        return $this;
    }


    protected function checkAndSetLabel($field, $message, $params)
    {
        if (isset($this->labels[$field])) {
            $message = str_replace('{field}', $this->labels[$field], $message);

            if (is_array($params)) {
                $i = 1;

                foreach ($params as $k => $v) {
                    $tag = '{field' . $i . '}';
                    $label = (isset($params[$k])
                        && (is_numeric($params[$k]) || is_string($params[$k]))
                        && isset($this->labels[$params[$k]]))
                            ? $this->labels[$params[$k]]
                            : $tag;

                    $message = str_replace($tag, $label, $message);
                    $i++;
                }
            }
        } else {
            $message = $this->prependLabels
                ? str_replace('{field}', ucwords(str_replace('_', ' ', $field)), $message)
                : str_replace('{field} ', '', $message);
        }

        return $message;
    }


    public function rules($rules)
    {
        foreach ($rules as $ruleType => $params) {
            if (is_array($params)) {
                foreach ($params as $innerParams) {
                    if (! is_array($innerParams)) {
                        $innerParams = (array) $innerParams;
                    }

                    array_unshift($innerParams, $ruleType);
                    call_user_func_array([$this, 'rule'], $innerParams);
                }
            } else {
                $this->rule($ruleType, $params);
            }
        }
    }


    public function withData($data, $fields = [])
    {
        $clone = clone $this;

        $clone->fields = empty($fields) ? $data : array_intersect_key($data, array_flip($fields));
        $clone->errors = [];

        return $clone;
    }


    public function mapFieldRules($field, $rules)
    {
        $me = $this;

        array_map(function ($rule) use ($field, $me) {
            $rule = (array) $rule;
            $ruleName = array_shift($rule);
            $message = null;

            if (isset($rule['message'])) {
                $message = $rule['message'];
                unset($rule['message']);
            }

            $added = call_user_func_array([$me, 'rule'], array_merge([$ruleName, $field], $rule));

            if (! empty($message)) {
                $added->message($message);
            }
        }, (array) $rules);
    }


    public function mapFieldsRules($rules)
    {
        $me = $this;

        array_map(function ($field) use ($rules, $me) {
            $me->mapFieldRules($field, $rules[$field]);
        }, array_keys($rules));
    }

    private function isAssociativeArray($input)
    {
        return count(array_filter(array_keys($input), 'is_string')) > 0;
    }
}
