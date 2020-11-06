<?php

namespace ZV;

use Exception;

/**
 * Class ZView
 * Author: dj
 * Date: 2020/7/16
 * Time: 下午12:59.
 */
class ZView {
    private $conf             = [];

    private $vars             = [];

    private $last_object_file = '';

    private $last_used_time   = 0;

    private $echo             = false;

    /**
     * @var string
     *             $abc[a][b][$c] legal
     *             $abc[$a[b]]    illegal
     */
    private $var_regexp = "\\@?\\\$[a-zA-Z_]\\w*(?:\\[[\\w\\.\"\\'\\-\$]+\\])*";

    /**
     * variable tag regexp.
     *
     * @var string
     */
    private $vtag_regexp = "\\<\\?=(\\@?\\\$[a-zA-Z_]\\w*(?:\\[[\\w\\.\"\\'\\[\\]\$]+\\])*)\\?\\>";

    /*private $isset_regexp = '<\?php echo isset\(.+?\) \? (?:.+?) : \'\';\?>';*/

    /**
     * const regexp.
     *
     * @var string
     */
    private $const_regexp = '\\{([\\w]+)\\}';

    /**
     * eval regexp.
     *
     * @var string
     */
    private $eval_regexp = '#(?:<!--\\{(eval))\\s+?(.*?)\\s*\\}-->#is';

    /**
     * tag search.
     *
     * @var array
     */
    private $tag_search = [];

    /**
     * tag replace.
     *
     * @var array
     */
    private $tag_replace = [];

    /**
     * sub templates.
     *
     * @var array
     */
    private $sub_tpl = [];

    public function __construct($conf, $echo = false) {
        $this->conf = array_merge([
            'tpl_ext'    => '.htm',
            'tpl_prefix' => 'tpl',
            'tmp_path'   => __DIR__ . '/runtime/tmp/',
            'force'      => 3, // force update template
            'view_path'  => [
                // search from path
                __DIR__ . '/view/',
            ],
        ], $conf);
        $this->echo = $echo;
    }

    /**
     * @param $key
     * @param $value
     */
    public function assign($key, &$value) {
        $this->vars[$key] = $value;
        return $this;
    }

    /**
     * @param $key_values
     */
    public function bind($key_values) {
        foreach ($key_values as $key => $value) {
            $this->vars[$key] = $value;
        }
        return $this;
    }

    /**
     * find template in view path & get compile template.
     *
     * @param $filename
     *
     * @throws Exception
     * @return string
     */
    public function get_compile_tpl($filename) {
        $obj_file      = $this->get_compile_path($filename);
        $is_obj_exists = is_file($obj_file);
        if ($is_obj_exists) {
            if ($this->conf['force'] > 1 && rand(0, $this->conf['force']) == $this->conf['force']) {
                // refresh object file
            } else {
                return $obj_file;
            }
        }
        // search directory
        if (is_file($filename)) {
            $file = $filename;
        } else {
            $file = $this->find_origin_path($filename);
        }
        // if origin path not exists, then check compile path
        if (empty($file)) {
            if ($is_obj_exists) {
                return $obj_file;
            }
            throw new Exception("template not found: {$filename}");
        }
        $file_mtime_old = $file_mtime = 0;
        if ($is_obj_exists) {
            // compare modify time of file
            $file_mtime = filemtime($file);
            if (! $file_mtime) {
                throw new Exception("template stat error: {$filename} ");
            }
            $file_mtime_old = $is_obj_exists ? filemtime($obj_file) : 0;
        }

        if (! $is_obj_exists || $file_mtime_old < $file_mtime) {
            // create tmp path
            ! is_dir($this->conf['tmp_path']) && mkdir($this->conf['tmp_path'], 0755, 1);
            // compile template
            $this->compile($file, $obj_file);
        }
        return $obj_file;
    }

    /**
     * search view path to find tpl path.
     *
     * @param $filename
     *
     * @return string
     */
    public function get_tpl($filename) {
        if (! isset($filename[1])) {
            return '';
        }
        $filepath = $this->find_origin_path($filename);
        // todo check sub template time for update
        $this->sub_tpl[$filepath] = filemtime($filepath);
        return file_get_contents($filepath);
    }

    /**
     * show template.
     *
     * @param $file
     *
     * @throws Exception
     */
    public function show($file) {
        $start_time = microtime(1);
        //error_reporting(E_ALL);
        //log::info(1, $file);
        $this->last_object_file = $this->get_compile_tpl($file);
        //log::info(2, $object_file);
        extract($this->vars);
        /*$object_body = file_get_contents($object_file);
        $object_body = base64_encode($object_body);
        include("data://text/plain;base64," . $object_file);*/

        include $this->last_object_file;

        $this->last_used_time = microtime(1) - $start_time;
    }

    /**
     * get debug info.
     *
     * @return array
     */
    public function get_debug_info() {
        return [
            'object_file' => $this->last_object_file,
            'use_time'    => $this->last_used_time,
        ];
    }

    /**
     * get compile template object file.
     *
     * @param $filename
     *
     * @return string
     */
    private function get_compile_path($filename) {
        $filename     = $this->get_template_ext($filename);
        $fix_filename = strtr($filename, ['/' => '#', '\\' => '#', ':' => '#']);
        $cache_prefix = $this->conf['tpl_prefix'];
        return $this->conf['tmp_path'] . $cache_prefix . '_' . $fix_filename . '.php';
    }

    /**
     * get compile template by ext.
     *
     * @param $filename
     *
     * @return string
     */
    private function get_template_ext($filename) {
        if (strpos($filename, '.') === false) {
            $filename .= $this->conf['tpl_ext'];
        }
        return $filename;
    }

    /**
     * find exists template path.
     *
     * @param $filename
     *
     * @return string
     */
    private function find_origin_path($filename) {
        $filename = $this->get_template_ext($filename);
        foreach ($this->conf['view_path'] as $path) {
            if (is_file($path . $filename)) {
                $file = $path . $filename;
                break;
            }
        }
        return $file;
    }

    /**
     * compile template.
     *
     * @param $file
     * @param $obj_file
     *
     * @throws Exception
     * @return mixed
     */
    private function compile($file, $obj_file) {
        $s = file_get_contents($file);
        // load sub template
        for ($i = 0; $i < 4; ++$i) {
            $s = preg_replace_callback('#<!--{template\\s+([^}]*?)}-->#i', [$this, 'get_tpl'], $s);
        }
        // compile block
        $this->compile_block($s);

        // replace variable by regexp
        $s = preg_replace('#(\\{' . $this->var_regexp . '\\}|' . $this->var_regexp . ')#i', '<?=\\1?>', $s);
        if (strpos($s, '<?={') !== false) {
            $s = preg_replace('#\\<\\?={(.+?)}\\?\\>#', '<?=\\1?>', $s);
        }

        // fix $data[key] -> $data['key']
        $s = preg_replace_callback('#\\<\\?=(\\@?\\$[a-zA-Z_]\\w*)((\\[[^\\]]+\\])+)\\?\\>#is', [$this, 'array_index'], $s);

        // loop
        for ($i = 0; $i < 4; ++$i) {
            $s = preg_replace_callback("#\\{loop\\s+{$this->vtag_regexp}\\s+{$this->vtag_regexp}\\s+{$this->vtag_regexp}\\}(.+?)\\{\\/loop\\}#is", [$this, 'loop_section'], $s);
            $s = preg_replace_callback("#\\{loop\\s+{$this->vtag_regexp}\\s+{$this->vtag_regexp}\\}(.+?)\\{\\/loop\\}#is", [$this, 'loop_section'], $s);
        }
        // if / elseif
        $s = preg_replace_callback('#\\{(if|elseif)\\s+(.*?)\\}#is', [$this, 'stripvtag_callback'], $s);

        // else
        $s = preg_replace('#\\{else\\}#is', '<?}else { ?>', $s);
        // end if
        $s = preg_replace('#\\{\\/(if)\\}#is', '<?}?>', $s);
        // end block
        $s = preg_replace('#\\{\\/(block)\\}#is', '<?}?>', $s);
        // {else}
        $s = preg_replace('#' . $this->const_regexp . '#', '<?=\\1?>', $s);

        // check key of array
        $s = preg_replace_callback("#\\<\\?=\\@(\\\$[a-zA-Z_]\\w*)((\\[[\\$\\[\\]\\w\\']+\\])+)\\?\\>#is", [$this, 'array_keyexists'], $s);
        // replace special tags
        if ($this->tag_search) {
            $s = str_replace($this->tag_search, $this->tag_replace, $s);
            // maybe eval in javascript
            if (strpos($s, '<!--[') !== false) {
                $s = str_replace($this->tag_search, $this->tag_replace, $s);
            }
        }
        // compile output function
        $s = $this->compile_output($s, '$this->rewrite_echo');

        if (! file_put_contents($obj_file, $s)) {
            throw new Exception('template stat error: ' . $file);
        }
        return $obj_file;
    }

    /**
     * compile output.
     *
     * @param $s
     * @param $out_method
     *
     * @return string
     */
    private function compile_output(&$s, $out_method) {
        $tokens        = token_get_all($s);
        $print_not_end = 0;
        foreach ($tokens as $token_index => &$token) {
            if (is_string($token)) {
                if ($token == ';' && $print_not_end) {
                    $token         = ')' . $token;
                    $print_not_end = 0;
                }
                $token = [
                    'content' => $token,
                ];
                continue;
            }
            list($type) = $token;
            switch ($type) {/*
        case T_OPEN_TAG:
            $token['content'] =
            break;*/
                case T_CLOSE_TAG:
                    if ($print_not_end) {
                        $token['content'] = ($print_not_end ? ')' : '') . $token[1];
                        $print_not_end    = 0;
                    } else {
                        $token['content'] = $token[1];
                    }
                    break;
                case T_OPEN_TAG_WITH_ECHO:
                    $token['content'] = '<?php ' . $out_method . '(';
                    $print_not_end    = 1;
                    break;
                case T_ECHO:
                    $token['content'] = $out_method;
                    if (! $this->find_next_token($tokens, $token_index + 1, ['('], [T_WHITESPACE])) {
                        $token['content'] .= '(';
                        $print_not_end    = 1;
                    }
                    break;
                case T_WHITESPACE:
                    $token['content'] = ' ';
                    //unset($tokens[$token_index]);
                    break;
                case T_INLINE_HTML:
                    $token_content = $token[1] ?? $token;
                    //$token_content = strtr($token_content, [/*'$' => '\\$',*/ '?'.'>' => '\\?'.'>']);
                    $token_content = strtr($token_content, ['\\' => '\\\\', '\'' => '\\\'']);
                    $token_content = sprintf('<?php ' . $out_method . '(\'%s\')' . ';?' . '>', $token_content);
                    /*
                    if (strpos($token_content, "\n") !== FALSE) {
                        $token_content = '<?php ' . $out_method . sprintf('(<<<__OUT__
%s
__OUT__)', $token_content) . ';?' . '>';
                    } else {
                        }*/
                    $token['content'] = $token_content;
                    break;
                default:
                    $token_content    = $token[1] ?? $token;
                    $token['content'] = $token_content;
                    break;
            }
        }
        $s = array_column($tokens, 'content');
        return implode('', $s);
    }

    /**
     * find next token.
     *
     * @param $list
     * @param $index
     * @param $keywords
     * @param mixed $skip_type
     *
     * @return int
     */
    private function find_next_token(&$list, $index, $keywords, $skip_type = []) {
        $len = count($list);
        while (++$index && $index < $len) {
            if ($list[$index][0] && in_array($list[$index][0], $skip_type)) {
                continue;
            }
            $str     = isset($list[$index]['content']) ? $list[$index]['content'] : (isset($list[$index][1]) ? $list[$index][1] : $list[$index]);
            $keyword = trim($str);
            if (! $keyword) {
                continue;
            }
            if (in_array(strtolower($keyword), $keywords)) {
                return 1;
            }
            return 0;
        }
        return 0;
    }

    /**
     * fix array index.
     *
     * @param $matches
     *
     * @return string
     */
    private function array_index($matches) {
        $name  = $matches[1];
        $items = $matches[2];
        if (strpos($items, '$') === false) {
            $items = preg_replace('#\\[([$a-zA-Z_][\\w$]*)\\]#is', "['\\1']", $items);
        } else {
            $items = preg_replace('#\\[([$a-zA-Z_][\\w$]*)\\]#is', '["\\1"]', $items);
        }
        return '<?=' . $name . $items . '?>';
    }

    /**
     * process tpl.
     *
     * @param $s
     */
    private function compile_block(&$s) {
        // replace eval block
        $s = preg_replace_callback($this->eval_regexp, [$this, 'stripvtag_callback'], $s);
        // remove template comment
        $s = preg_replace('#<!--\\#(.+?)-->#s', '', $s);
        // replace dynamic tag
        $s = preg_replace('#<!--{(.+?)}-->#s', '{\\1}', $s);
        // replace block
        $s = preg_replace_callback("#{block[\\s]+(\\w+[^\r\n]+)}#is", [$this, 'blocktag_callback'], $s);
        $s = preg_replace("#{(block_\\w+[^\r\n]+)}#is", '{$$1}', $s);
        // replace function
        $s = preg_replace_callback('#{((\\$?(?:\w+[\:\->]+)?\w+)\([^};]*?\);?)}#is', [$this, 'funtag_callback'], $s);
    }

    /**
     * fix echo array index key.
     *
     * @param $name
     * @param $items
     *
     * @return string
     */
    private function array_keyexists($name, $items) {
        return "<? echo isset({$name}{$items})?{$name}{$items}:'';?>";
    }

    /**
     * strip tag.
     *
     * @param $matchs
     *
     * @return mixed|string
     */
    private function stripvtag_callback($matchs) {
        $pre = $matchs[1];
        $s   = $matchs[2];
        switch ($pre) {
            case 'eval':
                $s                   = '<? ' . $s . '?' . '>';
                $search              = '<!--[eval=' . count($this->tag_search) . ']-->';
                $this->tag_search[]  = $search;
                $this->tag_replace[] = $this->stripvtag($s);
                return $search;
                break;
            case 'elseif':
                $s = '<? } elseif(' . $s . ') { ?>';
                break;
            case 'if':
                $s = '<? if(' . $s . ') { ?>';
                break;
        }
        return $this->stripvtag($s);
    }

    /**
     * @param            $s
     * @param bool|false $instring
     *
     * @return mixed
     */
    private function stripvtag($s, $instring = false) {
        if (strpos($s, '<? echo isset') !== false) {
            $s = preg_replace('#<\? echo isset\((.*?)\) \? (\\1) : \'\';\?>#is', $instring ? '{\\1}' : '\\1', $s);
        }
        return preg_replace('/' . $this->vtag_regexp . '/is', '\\1', str_replace('\\"', '"', $s));
    }

    /**
     * @param $matches
     *
     * @return string
     */
    private function striptag_callback($matches) {
        if (trim($matches[2]) == '') {
            return $matches[0];
        }
        // skip script type is tpl
        if (stripos($matches[1], ' type="tpl"') !== false) {
            return $matches[0];
        }
        $search             = '<!--[script=' . count($this->tag_search) . ']-->';
        $this->tag_search[] = $search;
        // filter script comment
        $matches[0] = preg_replace('#(//[^\'";><]*$|/\*[\s\S]*?\*/)#im', '', $matches[0]);
        // replace variable and constant
        // e.g.
        // {$a} {$a[1]} {$a[desc]} {ROOT}
        $matches[0]          = preg_replace('#{((?:\$[\w\[\]]+)|(?:[A-Z_]+))}#s', '<' . '?php echo $1;?' . '>', $matches[0]);
        $this->tag_replace[] = $matches[0];
        return $search;
    }

    /**
     * function tag callback.
     *
     * @param $matchs
     *
     * @return string
     */
    private function funtag_callback($matchs) {
        $search              = '<!--[func=' . count($this->tag_search) . ']-->';
        $this->tag_search[]  = $search;
        $this->tag_replace[] = '<?php echo ' . $matchs[1] . '?>';
        return $search;
    }

    /**
     * block tag callback.
     *
     * @param $matchs
     *
     * @return string
     */
    private function blocktag_callback($matchs) {
        $search             = '<!--[block=' . count($this->tag_search) . ']-->';
        $func               = 'block_' . $matchs[1];
        $this->tag_search[] = $search;
        // block_func(...params){ = $block_func=function(...params){
        $this->tag_replace[] = '<? $' . preg_replace('#\(#is', '=function(', $func, 1) . '{?>';
        return $search;
    }

    /**
     * for loop.
     *
     * @param $matchs
     *
     * @return string
     */
    private function loop_section($matchs) {
        if (isset($matchs[4])) {
            $arr       = $matchs[1];
            $k         = $matchs[2];
            $v         = $matchs[3];
            $statement = $matchs[4];
        } else {
            $arr       = $matchs[1];
            $k         = '';
            $v         = $matchs[2];
            $statement = $matchs[3];
        }

        $arr       = $this->stripvtag($arr);
        $k         = $this->stripvtag($k);
        $v         = $this->stripvtag($v);
        $statement = str_replace('\\"', '"', $statement);
        return $k ? "<? if(!empty({$arr})) { foreach({$arr} as {$k}=>{$v}) {?>{$statement}<? }}?>" : "<? if(!empty({$arr})) { foreach({$arr} as {$v}) {?>{$statement}<? }} ?>";
    }

    /**
     * rewrite echo.
     *
     * @param mixed ...$args
     */
    private function rewrite_echo(...$args) {
        if ($this->echo) {
            $echo = $this->echo;
        } else {
            $echo = 'print';
        }
        foreach ($args as $text) {
            $echo($text);
        }
    }
}
