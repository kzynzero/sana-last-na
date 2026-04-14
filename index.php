<?php
// Simple entry point - serve the main app HTML
// The JS inside will check the session and redirect to login.html if needed
readfile(__DIR__ . '/index.html');
