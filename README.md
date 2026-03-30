# ToC Inline

Adds a `[toc]` token that generates a table of contents from headings (`<h1>`–`<h6>`) in the content.

## Overview

ToC Inline is a lightweight Drupal filter plugin that scans rendered HTML for headings and replaces a `[toc]` token with a structured table of contents and is enabled automatically by the install script for Basic Html and Full HTML.

It is designed for editorial convenience—drop a token, get a working ToC with anchor links. No blocks, no Views, no extra configuration overhead.

## Installation

Install via Composer:

`composer require cms-alchemy/toc-inline`

## Features

- Uses `[toc]` token anywhere in content
- Automatically scans `<h1>`–`<h6>` tags
- Generates unique IDs for headings when missing
- Deduplicates existing IDs safely
- Builds nested lists based on heading levels
- Outputs accessible markup (`<nav aria-label="Table of contents">`)
- No JavaScript required (server-side rendering)
