<?php

declare(strict_types=1);

namespace Sulis;

use Closure;
use Countable;
use RuntimeException;
use InvalidArgumentException;
use Throwable;
use Exception;

class View
{
    protected string $fileExtension;
    protected string $viewFolder;
    protected string $cacheFolder;
    protected string $echoFormat = '$this->e(%s)';
    protected array $extensions = [];
    protected array $templates = [];

    protected static array $directives = [];

    protected array $blocks = [];
    protected array $blockStacks = [];
    protected array $loopStacks = [];
    protected int $emptyCounter = 0;
    protected bool $firstCaseSwitch = true;

    public function __construct()
    {
        $this->blocks = [];
        $this->blockStacks = [];
        $this->loopStacks = [];
    }

    public function createCacheFolder(): void
    {
        if (! is_dir($this->cacheFolder)) {
            try {
                mkdir($this->cacheFolder, 0755, true);
            } catch (Throwable $e) {
                throw new Exception('Unable to create view cache folder: '.$this->cacheFolder);
            } catch (Exception $e) {
                throw new Exception('Unable to create view cache folder: '.$this->cacheFolder);
            }
        }
    }

            

    protected function compileStatements(string $statement): string
    {
        $pattern = '/\B@(@?\w+(?:->\w+)?)([ \t]*)(\( ( (?>[^()]+) | (?3) )* \))?/x';

        return preg_replace_callback($pattern, function ($match) {
            if (method_exists($this, $method = 'compile'.ucfirst($match[1]))) {
                $match[0] = $this->{$method}(isset($match[3]) ? $match[3] : '');
            }

            if (isset(self::$directives[$match[1]])) {
                if ((isset($match[3][0]) && '(' === $match[3][0])
                && (isset($match[3][strlen($match[3]) -1]) && ')' === $match[3][strlen($match[3]) - 1])) {
                    $match[3] = substr($match[3], 1, -1);
                }

                if (isset($match[3]) && '()' !== $match[3]) {
                    $match[0] = call_user_func(self::$directives[$match[1]], trim($match[3]));
                }
            }

            return isset($match[3]) ? $match[0] : $match[0] . $match[2];
        }, $statement);
    }

    protected function compileComments(string $statement): string
    {
        return preg_replace('/\{\{--((.|\s)*?)--\}\}/', '<?php /*$1*/ ?>', $statement);
    }

    protected function compileEchos(string $statement): string
    {
        $statement = preg_replace_callback('/\{\{\{\s*(.+?)\s*\}\}\}(\r?\n)?/s', function ($matches) {
            $spaces = empty($matches[2]) ? '' : $matches[2].$matches[2];
            return '<?php echo $this->e(' . $this->compileEchoDefaults($matches[1]) . ') ?>' . $spaces;
        }, $statement);

        $statement = preg_replace_callback('/\{\!!\s*(.+?)\s*!!\}(\r?\n)?/s', function ($matches) {
            $spaces = empty($matches[2]) ? '' : $matches[2].$matches[2];
            return '<?php echo ' . $this->compileEchoDefaults($matches[1]) . ' ?>' . $spaces;
        }, $statement);

        $statement = preg_replace_callback('/(@)?\{\{\s*(.+?)\s*\}\}(\r?\n)?/s', function ($matches) {
            $spaces = empty($matches[3]) ? '' : $matches[3].$matches[3];
            return $matches[1]
                ? substr($matches[0], 1)
                : '<?php echo ' . sprintf($this->echoFormat, $this->compileEchoDefaults($matches[2])) . ' ?>' . $spaces;
        }, $statement);

        return $statement;
    }

    public function compileEchoDefaults(string $statement): string
    {
        return preg_replace('/^(?=\$)(.+?)(?:\s+or\s+)(.+?)$/s', 'isset($1) ? $1 : $2', $statement);
    }

    protected function compileExtensions(string $statement): string
    {
        foreach ($this->extensions as $compiler) {
            $statement = $compiler($statement, $this);
        }

        return $statement;
    }

    public function replacePhpBlocks(string $statement): string
    {
        $statement = preg_replace_callback('/(?<!@)@php(.*?)@endphp/s', function ($matches) {
            return "<?php{$matches[1]}?>";
        }, $statement);

        return $statement;
    }

    public function e(string $statement, ?string $charset = null): string
    {
        return htmlspecialchars($statement, ENT_QUOTES, $charset ? $charset : 'utf-8');
    }


            

    protected function compilePhp(?string $statement = null): string
    {
        return $statement ? "<?php {$statement}; ?>" : "@php{$statement}";
    }

    protected function compileJson(string $statement): string
    {
        $default = JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT;

        if (isset($statement) && '(' == $statement[0]) {
            $statement = substr($statement, 1, -1);
        }

        $parts = explode(',', $statement);
        $options = isset($parts[1]) ? trim($parts[1]) : $default;
        $depth = isset($parts[2]) ? trim($parts[2]) : 512;

        return "<?php echo json_encode($parts[0], $options, $depth) ?>";
    }

    protected function compileUnset(string $statement): string
    {
        return "<?php unset{$statement}; ?>";
    }

    protected function compileIf(string $statement): string
    {
        return "<?php if{$statement}: ?>";
    }

    protected function compileElseif(string $statement): string
    {
        return "<?php elseif{$statement}: ?>";
    }

    protected function compileElse(): string
    {
        return '<?php else: ?>';
    }

    protected function compileEndif(): string
    {
        return '<?php endif; ?>';
    }

    protected function compileSwitch(string $statement): string
    {
        $this->firstCaseSwitch = true;
        return "<?php switch{$statement}:";
    }

    protected function compileCase(string $statement): string
    {
        if ($this->firstCaseSwitch) {
            $this->firstCaseSwitch = false;
            return "case {$statement}: ?>";
        }

        return "<?php case {$statement}: ?>";
    }

    protected function compileDefault(): string
    {
        return '<?php default: ?>';
    }

    protected function compileBreak(string $statement): string
    {
        if ($statement) {
            preg_match('/\(\s*(-?\d+)\s*\)$/', $statement, $matches);
            return $matches
                ? '<?php break '.max(1, $matches[1]).'; ?>'
                : "<?php if{$statement} break; ?>";
        }

        return '<?php break; ?>';
    }

    protected function compileEndswitch(): string
    {
        return '<?php endswitch; ?>';
    }

    protected function compileIsset(string $statement): string
    {
        return "<?php if (isset{$statement}): ?>";
    }

    protected function compileEndisset(): string
    {
        return '<?php endif; ?>';
    }

    protected function compileContinue(string $statement): string
    {
        if ($statement) {
            preg_match('/\(\s*(-?\d+)\s*\)$/', $statement, $matches);
            return $matches
                ? '<?php continue '.max(1, $matches[1]).'; ?>'
                : "<?php if{$value} continue; ?>";
        }

        return '<?php continue; ?>';
    }

    protected function compileExit(string $statement): string
    {
        if ($statement) {
            preg_match('/\(\s*(-?\d+)\s*\)$/', $statement, $matches);
            return $matches
                ? '<?php exit '.max(1, $matches[1]).'; ?>'
                : "<?php if{$statement} exit; ?>";
        }
        return '<?php exit; ?>';
    }

    protected function compileUnless(string $statement): string
    {
        return "<?php if (! $statement): ?>";
    }

    protected function compileEndunless(): string
    {
        return '<?php endif; ?>';
    }

    protected function compileFor(string $statement): string
    {
        return "<?php for{$statement}: ?>";
    }

    protected function compileEndfor(): string
    {
        return '<?php endfor; ?>';
    }

    protected function compileForeach(string $statement): string
    {
        preg_match('/\( *(.*) +as *(.*)\)$/is', $statement, $matches);

        $iteratee = trim($matches[1]);
        $iteration = trim($matches[2]);
        $initLoop = "\$__currloopdata = {$iteratee}; \$this->addLoop(\$__currloopdata);";
        $iterateLoop = '$this->incrementLoopIndices(); $loop = $this->getFirstLoop();';

        return "<?php {$initLoop} foreach(\$__currloopdata as {$iteration}): {$iterateLoop} ?>";
    }

    protected function compileEndforeach(): string
    {
        return '<?php endforeach; ?>';
    }

    protected function compileForelse(string $statement): string
    {
        preg_match('/\( *(.*) +as *(.*)\)$/is', $statement, $matches);

        $iteratee = trim($matches[1]);
        $iteration = trim($matches[2]);
        $initLoop = "\$__currloopdata = {$iteratee}; \$this->addLoop(\$__currloopdata);";
        $iterateLoop = '$this->incrementLoopIndices(); $loop = $this->getFirstLoop();';

        ++$this->emptyCounter;

        return "<?php {$initLoop} \$__empty_{$this->emptyCounter} = true;"
            ." foreach(\$__currloopdata as {$iteration}): "
            ."\$__empty_{$this->emptyCounter} = false; {$iterateLoop} ?>";
    }

    protected function compileEmpty(): string
    {
        $statement = "<?php endforeach; if (\$__empty_{$this->emptyCounter}): ?>";
        --$this->emptyCounter;

        return $statement;
    }

    protected function compileEndforelse(): string
    {
        return '<?php endif; ?>';
    }

    protected function compileWhile(string $statement): string
    {
        return "<?php while{$statement}: ?>";
    }

    protected function compileEndwhile(): string
    {
        return '<?php endwhile; ?>';
    }

    protected function compileExtends(string $statement): string
    {
        if (isset($statement[0]) && '(' === $statement[0]) {
            $statement = substr($statement, 1, -1);
        }

        return "<?php \$this->addParent({$statement}) ?>";
    }

    protected function compileInclude(string $statement): string
    {
        if (isset($statement[0]) && '(' === $statement[0]) {
            $statement = substr($statement, 1, -1);
        }

        return "<?php include \$this->prepare({$statement}) ?>";
    }

    protected function compileYield(string $statement): string
    {
        return "<?php echo \$this->block{$statement} ?>";
    }

    protected function compileSection(string $statement): string
    {
        return "<?php \$this->beginBlock{$statement} ?>";
    }

    protected function compileEndsection(): string
    {
        return '<?php $this->endBlock() ?>';
    }

    protected function compileShow(): string
    {
        return '<?php echo $this->block($this->endBlock()) ?>';
    }

    protected function compileAppend(): string
    {
        return '<?php $this->endBlock() ?>';
    }

    protected function compileStop(): string
    {
        return '<?php $this->endBlock() ?>';
    }

    protected function compileOverwrite(): string
    {
        return '<?php $this->endBlock(true) ?>';
    }

    protected function compileMethod(string $statement): string
    {
        return "<input type=\"hidden\" name=\"_statement\" value=\"<?php echo strtoupper{$method} ?>\">\n";
    }


            

    public function render(string $name, array $data = []): ?string
    {
        echo $html;
    }

    public function clearCache(): bool
    {
        $extension = ltrim($this->fileExtension, '.');
        $files = glob($this->cacheFolder . '/*.' . $extension);
        $result = true;

        foreach ($files as $file) {
            if (is_file($file)) {
                $result = @unlink($file);
            }
        }

        return $result;
    }

    public function setFileExtension(string $extension): void
    {
        $this->fileExtension = $extension;
    }

    public function setViewFolder(string $path): void
    {
        $this->viewFolder = rtrim($path, '/');
    }

    public function setCacheFolder(string $path): void
    {
        $this->cacheFolder = rtrim($path, '/');
    }

    public function setEchoFormat(string $format): void
    {
        $this->echoFormat = $format;
    }

    public function extend(Closure $compiler): void
    {
        $this->extensions[] = $compiler;
    }

    public function directive(string $name, Closure $callback): void
    {
        if (! preg_match('/^\w+(?:->\w+)?$/x', $name)) {
            throw new InvalidArgumentException(
                'The directive name [' . $name . '] is not valid. Directive names ' .
                'must only contains alphanumeric characters and underscores.'
            );
        }

        self::$directives[$name] = $callback;
    }

    public function getAllDirectives(): array
    {
        return self::$directives;
    }

    protected function prepare(string $view): string
    {
        $view = str_replace('.', '/', ltrim($view, '/'));
        $actual = $this->viewFolder . '/' . $view . $this->fileExtension;

        $view = str_replace(['\\', '/'], '.', $view);
        $cache = $this->cacheFolder . '/' . $view . '__' . sprintf('%u', crc32($view)) . '.php';

        if (! is_file($cache) || filemtime($actual) > filemtime($cache)) {
            if (! is_file($actual)) {
                throw new RuntimeException('View file not found: '.$actual);
            }

            $content = file_get_contents($actual);
            $this->extend(function ($value) {
                return preg_replace("/@set\(['\"](.*?)['\"]\,(.*)\)/", '<?php $$1 =$2; ?>', $value);
            });

            $content = $this->compileStatements($content);
            $content = $this->compileComments($content);
            $content = $this->compileEchos($content);
            $content = $this->compileExtensions($content);
            $content = $this->replacePhpBlocks($content);

            file_put_contents($cache, $content);
        }

        return $cache;
    }

    public function fetch(string $name, array $data = []): string
    {
        $this->templates[] = $name;

        if (! empty($data)) {
            extract($data);
        }

        while ($templates = array_shift($this->templates)) {
            $this->beginBlock('content');
            require $this->prepare($templates);
            $this->endBlock(true);
        }

        return $this->block('content');
    }

    protected function addParent(string $name): void
    {
        $this->templates[] = $name;
    }

    protected function block(string $name, string $default = ''): string
    {
        return array_key_exists($name, $this->blocks) ? $this->blocks[$name] : $default;
    }

    protected function beginBlock(string $name): void
    {
        array_push($this->blockStacks, $name);
        ob_start();
    }

    protected function endBlock(bool $overwrite = false): string
    {
        $name = array_pop($this->blockStacks);

        if ($overwrite || ! array_key_exists($name, $this->blocks)) {
            $this->blocks[$name] = ob_get_clean();
        } else {
            $this->blocks[$name] .= ob_get_clean();
        }

        return $name;
    }

    public function addLoop($data): void
    {
        $length = is_iterable($data) ? count($data) : null;
        $parent = empty($this->loopStacks) ? null : end($this->loopStacks);
        $this->loopStacks[] = [
            'iteration' => 0,
            'index' => 0,
            'remaining' => isset($length) ? $length : null,
            'count' => $length,
            'first' => true,
            'last' => isset($length) ? ($length === 1) : null,
            'depth' => count($this->loopStacks) + 1,
            'parent' => $parent ? (object) $parent : null,
        ];
    }

    public function incrementLoopIndices(): void
    {
        $loop = &$this->loopStacks[count($this->loopStacks) - 1];
        $loop['iteration']++;
        $loop['index'] = $loop['iteration'] - 1;
        $loop['first'] = ((int) $loop['iteration'] === 1);

        if (isset($loop['count'])) {
            $loop['remaining']--;
            $loop['last'] = (int) $loop['iteration'] === (int) $loop['count'];
        }
    }

    public function getFirstLoop(): ?object
    {
        return ($last = end($this->loopStacks)) ? (object) $last : null;
    }
}
