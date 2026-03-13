<?php
/**
 * FILE: includes/footer.php
 * PURPOSE: Shared page footer — closes the HTML opened by header.php.
 *
 * Also includes the minimal JS file.
 *
 * NOTE: The closing </main>, </div class="atm-screen">, </body>, </html>
 * tags here match the opening tags in header.php. Every page that includes
 * header.php MUST include footer.php or the HTML will be malformed.
 */
?>
    </main><!-- /atm-main -->

    <footer class="atm-footer">
        <span>SECUREPAY BANKING TERMINAL v1.0</span>
        <span>
            TRANSACTIONS ARE FINAL &nbsp;|&nbsp;
            SECURED BY 256-BIT ENCRYPTION &nbsp;|&nbsp;
            &copy; <?= date('Y') ?>
        </span>
    </footer>

</div><!-- /atm-screen -->

<!--
    JS file is loaded at the BOTTOM of the page (before </body>).
    WHY? The HTML is fully parsed before JS runs, so the page displays
    even if JS is slow to load. Also, JS that manipulates DOM elements
    needs those elements to exist first.
-->
<script src="/assets/script.js"></script>
</body>
</html>