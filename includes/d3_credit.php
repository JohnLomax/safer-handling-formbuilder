<?php
declare(strict_types=1);

/**
 * Footer credit used on public forms.
 */
function d3DigitalCreditHtml(): string
{
    return <<<'HTML'
<p class="d3-credit">
  Created by
  <a href="https://d3-digital.com" target="_blank" rel="noopener noreferrer">D3 Digital</a>
</p>
HTML;
}

/**
 * Minimal CSS for the public-form credit line.
 */
function d3DigitalCreditCss(): string
{
    return <<<'CSS'
.d3-credit {
  margin: 18px 0 0;
  text-align: center;
  font-size: 0.82rem;
  color: #5a7fa0;
  line-height: 1.4;
}
.d3-credit a {
  color: #0255a4;
  font-weight: 600;
  text-decoration: none;
}
.d3-credit a:hover {
  text-decoration: underline;
}
CSS;
}
