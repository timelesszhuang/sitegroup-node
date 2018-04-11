<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    {$d['tdk']}
</head>
<body>
<style>
    body {
        color: #080808;
    }

    .container {
        width: 700px;
        margin: 0px auto;
        border: 1px solid #dddee1;
        border-color: #e9eaec;
        background: #fff;
        border-radius: 4px;
        box-shadow: 10px 10px 5px #888888;
    }

    li {
        font-size: 14px;
        padding: 0 10px;
        line-height: 34px;
        list-style: none;
        display: inline-block;
        color: #080808;
    }

    .mainsite {
        display: block;
        font-size: 16px;
    }

    li a {
        text-decoration: none;
        color: #055263;
    }

    li a:hover {
        position: relative;
        left: 1px;
        top: 1px;
        color: #0b46a7;
    }
</style>
<div class="container">
    <ul>
        <?php
        foreach ($d['childtreesite'] as $k => $v) {
            ?>
            <li class="<?php
            if (array_key_exists('childsite', $v)) { ?>
            mainsite
             <?php
            }
            ?>">
                <a href="{$v['url']}">{$v['name']}</a>
                <ul>
                    <?php
                    if (array_key_exists('childsite', $v)) {
                        foreach ($v['childsite'] as $ke => $va) {
                            ?>
                            <li class="<?php
                            if (array_key_exists('childsite', $va)) { ?>
                            mainsite
                             <?php
                            }
                            ?>">
                                <a href="{$va['url']}">{$va['name']}</a>
                                <ul>
                                    <?php
                                    if (array_key_exists('childsite', $va)) {
                                        foreach ($va['childsite'] as $key => $val) {
                                            ?>
                                            <li><a href="{$val['url']}">{$val['name']}</a></li>
                                            <?php
                                        }
                                    }
                                    ?>
                                </ul>
                            </li>
                            <?php
                        }
                    }
                    ?>
                </ul>
            </li>
            <?php
        }
        ?>
    </ul>
</div>
</body>
</html>