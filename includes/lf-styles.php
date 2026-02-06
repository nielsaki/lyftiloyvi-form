<?php

if (!defined('ABSPATH')) {
    exit;
}

function lf_enqueue_styles()
{
    $css = '
    .lf-form {
        max-width: 900px;
        margin: 2rem auto 3rem;
        padding: 1.75rem 2.5rem 2.5rem;
        background: #ffffff;
        border-radius: 10px;
        box-shadow: 0 6px 18px rgba(0,0,0,0.06);
        box-sizing: border-box;
    }
    .lf-form-title {
        margin: 0 0 1rem;
        font-size: 1.4rem;
        font-weight: 700;
        border-bottom: 1px solid #e5e5e5;
        padding-bottom: 0.5rem;
    }
    .lf-form p {
        margin: 0 0 1rem;
    }
   .lf-form label {
        display: block;
        font-weight: 600;
        margin-bottom: 0.25rem;
    }
    .lf-info-block {
        background: #f8f9fa;
        border-radius: 6px;
        padding: 0.75rem 1rem;
        border: 1px solid #e2e4e7;
        font-size: 13px;
        line-height: 1.5;
    }
    .lf-guardian-block {
        margin-top: 1rem;
        padding: 0.75rem 1rem;
        border-radius: 6px;
        background: #fdfdfd;
        border: 1px dashed #e2e4e7;
    }

    .lf-row {
        display: flex;
        flex-wrap: wrap;
        gap: 1.5rem;
    }
    .lf-col {
        flex: 1 1 0;
        min-width: 0;
    }
    .lf-col-center {
        flex: 0 0 auto;
        min-width: 180px;
        text-align: center;
    }
    .lf-col-center .lf-inline-options {
        justify-content: center;
    }
    .lf-inline-options {
        display: flex;
        align-items: center;
        gap: 1.5rem;
        margin-top: 0.25rem;
    }
    .lf-inline-options .lf-radio-option {
        display: inline-flex;
        align-items: center;
        font-weight: 400;
        margin: 0;
    }
    .lf-form input[type="text"],
    .lf-form input[type="email"],
    .lf-form input[type="date"],
    .lf-form select {
        width: 100%;
        padding: 0.5em 0.6em;
        border-radius: 4px;
        border: 1px solid #ccd0d4;
        box-sizing: border-box;
        font-size: 14px;
        font-family: inherit;
        background-color: #fff;
    }
    .lf-form input[type="text"]:focus,
    .lf-form input[type="email"]:focus,
    .lf-form input[type="date"]:focus,
    .lf-form select:focus {
        outline: none;
        border-color: #007cba;
        box-shadow: 0 0 0 1px #007cba33;
    }
    .lf-form select:disabled {
        background-color: #f3f4f5;
        color: #888;
        cursor: not-allowed;
    }
    .lf-form button[type="submit"] {
        display: inline-block;
        padding: 0.7rem 1.6rem;
        border-radius: 4px;
        border: none;
        background: #007cba;
        color: #ffffff;
        font-size: 14px;
        font-weight: 600;
        cursor: pointer;
        transition: background 0.15s ease, transform 0.05s ease, box-shadow 0.15s ease;
    }
    .lf-form button[type="submit"]:hover {
        background: #006ba1;
        box-shadow: 0 2px 6px rgba(0,0,0,0.15);
    }
    .lf-form button[type="submit"]:active {
        transform: translateY(1px);
        box-shadow: none;
    }
    .lf-form input[type="checkbox"],
    .lf-form input[type="radio"] {
        width: auto;
        margin-right: 0.4rem;
    }
    .lf-form .lf-hp {
        display: none;
    }
    .lf-success {
        padding: 0.6em 0.9em;
        margin: 1rem auto;
        border-radius: 4px;
        border: 1px solid #4caf50;
        background: #e8f5e9;
        color: #256029;
        max-width: 900px;
    }
    .lf-error {
        padding: 0.6em 0.9em;
        margin: 1rem auto;
        border-radius: 4px;
        border: 1px solid #f44336;
        background: #ffebee;
        color: #b71c1c;
        max-width: 900px;
    }
    .lf-error ul {
        margin: 0.25rem 0 0;
        padding-left: 1.2rem;
    }
    .lf-error li {
        margin: 0.15rem 0;
    }
    @media (max-width: 600px) {
        .lf-form {
            margin: 1.5rem 1rem 2.5rem;
            padding: 1.4rem 1.4rem 2rem;
        }
        .lf-row {
            flex-direction: column;
        }
    }
    ';
    wp_add_inline_style('wp-block-library', $css);
}
add_action('wp_enqueue_scripts', 'lf_enqueue_styles');

