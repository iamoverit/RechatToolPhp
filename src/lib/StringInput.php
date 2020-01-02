<?php


namespace Rechat\lib;


use Symfony\Component\Console\Exception\InvalidArgumentException;
use Symfony\Component\Console\Exception\RuntimeException;
use Symfony\Component\Console\Input\InputDefinition;

class StringInput extends \Symfony\Component\Console\Input\Input
{
    const REGEX_STRING = '([^\s]+?)(?:\s|(?<!\\\\)"|(?<!\\\\)\'|$)';
    const REGEX_QUOTED_STRING = '(?:"([^"\\\\]*(?:\\\\.[^"\\\\]*)*)"|\'([^\'\\\\]*(?:\\\\.[^\'\\\\]*)*)\')';

    private $tokens;
    private $parsed;

    /**
     * @param string $input A string representing the parameters from the CLI
     */
    public function __construct(string $input)
    {
        parent::__construct();

        $this->setTokens($this->tokenize($input));
    }

    protected function setTokens(array $tokens)
    {
        $this->tokens = $tokens;
    }

    /**
     * Tokenizes a string.
     *
     * @throws InvalidArgumentException When unable to parse input (should never happen)
     */
    private function tokenize(string $input): array
    {
        $tokens = [];
        $length = \strlen($input);
        $cursor = 0;
        while ($cursor < $length) {
            if (preg_match('/\s+/A', $input, $match, null, $cursor)) {
            } elseif (preg_match(
                '/([^="\'\s]+?)(=?)('.self::REGEX_QUOTED_STRING.'+)/A',
                $input,
                $match,
                null,
                $cursor
            )) {
                $tokens[] = $match[1].$match[2].stripcslashes(
                        str_replace(['"\'', '\'"', '\'\'', '""'], '', substr($match[3], 1, \strlen($match[3]) - 2))
                    );
            } elseif (preg_match('/'.self::REGEX_QUOTED_STRING.'/A', $input, $match, null, $cursor)) {
                $tokens[] = stripcslashes(substr($match[0], 1, \strlen($match[0]) - 2));
            } elseif (preg_match('/'.self::REGEX_STRING.'/A', $input, $match, null, $cursor)) {
                $tokens[] = stripcslashes($match[1]);
            } else {
                // should never happen
                throw new InvalidArgumentException(
                    sprintf('Unable to parse input near "... %s ..."', substr($input, $cursor, 10))
                );
            }

            $cursor += \strlen($match[0]);
        }

        return $tokens;
    }

    protected function parse()
    {
        $parseOptions = true;
        $this->parsed = $this->tokens;
        while (null !== $token = array_shift($this->parsed)) {
            try {
                if ($parseOptions && '' == $token) {
                    $this->parseArgument($token);
                } elseif ($parseOptions && '--' == $token) {
                    $parseOptions = false;
                } elseif ($parseOptions && 0 === strpos($token, '--')) {
                    $this->parseLongOption($token);
                } elseif ($parseOptions && '-' === $token[0] && '-' !== $token) {
                    $this->parseShortOption($token);
                } else {
                    $this->parseArgument($token);
                }
            } catch (\Throwable $t) {
                //
            }
        }
    }

    /**
     * Parses a long option.
     */
    private function parseLongOption(string $token)
    {
        $name = substr($token, 2);

        if (false !== $pos = strpos($name, '=')) {
            if (0 === \strlen($value = substr($name, $pos + 1))) {
                array_unshift($this->parsed, $value);
            }
            $this->addLongOption(substr($name, 0, $pos), $value);
        } else {
            $this->addLongOption($name, null);
        }
    }

    /**
     * Adds a long option value.
     *
     * @throws RuntimeException When option given doesn't exist
     */
    private function addLongOption(string $name, $value)
    {
        if (!$this->definition->hasOption($name)) {
            throw new RuntimeException(sprintf('The "--%s" option does not exist.', $name));
        }

        $option = $this->definition->getOption($name);

        if (null !== $value && !$option->acceptValue()) {
            throw new RuntimeException(sprintf('The "--%s" option does not accept a value.', $name));
        }

        if (\in_array($value, ['', null], true) && $option->acceptValue() && \count($this->parsed)) {
            // if option accepts an optional or mandatory argument
            // let's see if there is one provided
            $next = array_shift($this->parsed);
            if ((isset($next[0]) && '-' !== $next[0]) || \in_array($next, ['', null], true)) {
                $value = $next;
            } else {
                array_unshift($this->parsed, $next);
            }
        }

        if (null === $value) {
            if ($option->isValueRequired()) {
                throw new RuntimeException(sprintf('The "--%s" option requires a value.', $name));
            }

            if (!$option->isArray() && !$option->isValueOptional()) {
                $value = true;
            }
        }

        if ($option->isArray()) {
            $this->options[$name][] = $value;
        } else {
            $this->options[$name] = $value;
        }
    }

    /**
     * Parses a short option.
     */
    private function parseShortOption(string $token)
    {
        $name = substr($token, 1);

        if (\strlen($name) > 1) {
            if ($this->definition->hasShortcut($name[0]) && $this->definition->getOptionForShortcut(
                    $name[0]
                )->acceptValue()) {
                // an option with a value (with no space)
                $this->addShortOption($name[0], substr($name, 1));
            } else {
                $this->parseShortOptionSet($name);
            }
        } else {
            $this->addShortOption($name, null);
        }
    }

    /**
     * Adds a short option value.
     *
     * @throws RuntimeException When option given doesn't exist
     */
    private function addShortOption(string $shortcut, $value)
    {
        if (!$this->definition->hasShortcut($shortcut)) {
            throw new RuntimeException(sprintf('The "-%s" option does not exist.', $shortcut));
        }

        $this->addLongOption($this->definition->getOptionForShortcut($shortcut)->getName(), $value);
    }

    /**
     * Parses a short option set.
     *
     * @throws RuntimeException When option given doesn't exist
     */
    private function parseShortOptionSet(string $name)
    {
        $len = \strlen($name);
        for ($i = 0; $i < $len; ++$i) {
            if (!$this->definition->hasShortcut($name[$i])) {
                $encoding = mb_detect_encoding($name, null, true);
                throw new RuntimeException(sprintf('The "-%s" option does not exist.', false === $encoding ? $name[$i] : mb_substr($name, $i, 1, $encoding)));
            }

            $option = $this->definition->getOptionForShortcut($name[$i]);
            if ($option->acceptValue()) {
                $this->addLongOption($option->getName(), $i === $len - 1 ? null : substr($name, $i + 1));

                break;
            } else {
                $this->addLongOption($option->getName(), null);
            }
        }
    }

    private function parseArgument(string $token)
    {
        $c = \count($this->arguments);

        // if input is expecting another argument, add it
        if ($this->definition->hasArgument($c)) {
            $arg = $this->definition->getArgument($c);
            $this->arguments[$arg->getName()] = $arg->isArray() ? [$token] : $token;

            // if last argument isArray(), append token to last argument
        } elseif ($this->definition->hasArgument($c - 1) && $this->definition->getArgument($c - 1)->isArray()) {
            $arg = $this->definition->getArgument($c - 1);
            $this->arguments[$arg->getName()][] = $token;

            // unexpected argument
        } else {
            $all = $this->definition->getArguments();
            if (\count($all)) {
                throw new RuntimeException(sprintf('Too many arguments, expected arguments "%s".', implode('" "', array_keys($all))));
            }

            throw new RuntimeException(sprintf('No arguments expected, got "%s".', $token));
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getFirstArgument()
    {
        $isOption = false;
        foreach ($this->tokens as $i => $token) {
            if ($token && '-' === $token[0]) {
                if (false !== strpos($token, '=') || !isset($this->tokens[$i + 1])) {
                    continue;
                }

                // If it's a long option, consider that everything after "--" is the option name.
                // Otherwise, use the last char (if it's a short option set, only the last one can take a value with space separator)
                $name = '-' === $token[1] ? substr($token, 2) : substr($token, -1);
                if (!isset($this->options[$name]) && !$this->definition->hasShortcut($name)) {
                    // noop
                } elseif ((isset($this->options[$name]) || isset(
                            $this->options[$name = $this->definition->shortcutToName(
                                $name
                            )]
                        )) && $this->tokens[$i + 1] === $this->options[$name]) {
                    $isOption = true;
                }

                continue;
            }

            if ($isOption) {
                $isOption = false;
                continue;
            }

            return $token;
        }

        return null;
    }

    /**
     * {@inheritdoc}
     */
    public function hasParameterOption($values, bool $onlyParams = false)
    {
        $values = (array)$values;

        foreach ($this->tokens as $token) {
            if ($onlyParams && '--' === $token) {
                return false;
            }
            foreach ($values as $value) {
                // Options with values:
                //   For long options, test for '--option=' at beginning
                //   For short options, test for '-o' at beginning
                $leading = 0 === strpos($value, '--') ? $value.'=' : $value;
                if ($token === $value || '' !== $leading && 0 === strpos($token, $leading)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function getParameterOption($values, $default = false, bool $onlyParams = false)
    {
        $values = (array)$values;
        $tokens = $this->tokens;

        while (0 < \count($tokens)) {
            $token = array_shift($tokens);
            if ($onlyParams && '--' === $token) {
                return $default;
            }

            foreach ($values as $value) {
                if ($token === $value) {
                    return array_shift($tokens);
                }
                // Options with values:
                //   For long options, test for '--option=' at beginning
                //   For short options, test for '-o' at beginning
                $leading = 0 === strpos($value, '--') ? $value.'=' : $value;
                if ('' !== $leading && 0 === strpos($token, $leading)) {
                    return substr($token, \strlen($leading));
                }
            }
        }

        return $default;
    }

}