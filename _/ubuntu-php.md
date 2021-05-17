---
title: "Ubuntu下编译PHP所需的依赖库"
date: 2018-06-13 15:52:22
tags: PHP
---

#### 编译环境

`sudo apt-get install -y build-essential`

#### xml

`sudo apt-get install -y libxml2-dev`

#### pcre

`sudo apt-get install -y libpcre3-dev`

#### jpeg

`sudo apt-get install -y libjpeg62-dev`

<!--more-->

#### freetype

`sudo apt-get install -y libfreetype6-dev`

#### png

`sudo apt-get install -y libpng12-dev libpng3 libpnglite-dev`

#### iconv

`sudo apt-get install -y libiconv-hook-dev libiconv-hook1`

#### mycrypt

`sudo apt-get install -y libmcrypt-dev libmcrypt4`

#### mhash

`sudo apt-get install -y libmhash-dev libmhash2`

#### openssl

`sudo apt-get install -y libltdl-dev libssl-dev`

#### curl

`sudo apt-get install -y libcurl4-openssl-dev`

#### mysql

`sudo apt-get install -y libmysqlclient-dev`

#### imagick

`sudo apt-get install -y libmagickcore-dev libmagickwand-dev`

#### readline

`sudo apt-get install -y libedit-dev`

#### ubuntu 无法找到 iconv

`sudo ln -s /usr/lib/libiconv_hook.so.1.0.0 /usr/lib/libiconv.so`
`sudo ln -s /usr/lib/libiconv_hook.so.1.0.0 /usr/lib/libiconv.so.1`

#### 安装PHP扩展

`sudo apt-get install -y autoconf automake m4`