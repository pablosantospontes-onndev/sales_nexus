<section class="auth-split">
    <div class="auth-split-topbar">
        <?= view('auth/_theme_switch') ?>
    </div>

    <?= view('auth/_visual') ?>

    <section class="auth-form-side">
        <div class="auth-login-card">
            <div class="auth-login-head">
                <p class="eyebrow auth-login-eyebrow">
                    <span class="auth-login-eyebrow-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24" focusable="false">
                            <path d="M17 9h-1V7a4 4 0 0 0-8 0v2H7a2 2 0 0 0-2 2v8a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2v-8a2 2 0 0 0-2-2Zm-6 0V7a2 2 0 1 1 4 0v2Z"></path>
                        </svg>
                    </span>
                    <span>Acesso seguro</span>
                </p>
                <h2>Fa&ccedil;a seu login</h2>
                <p>Entre com seu CPF e senha para acessar o CRM.</p>
            </div>

            <form method="post" class="auth-login-form">
                <?= \App\Core\Csrf::input() ?>

                <label class="auth-field">
                    <span>CPF</span>
                    <span class="auth-input-shell">
                        <span class="auth-input-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" focusable="false">
                                <path d="M12 12a4 4 0 1 0-4-4 4 4 0 0 0 4 4Zm0 2c-4.42 0-8 2.24-8 5v1h16v-1c0-2.76-3.58-5-8-5Z"></path>
                            </svg>
                        </span>
                        <input type="text" name="cpf" value="<?= e($cpf) ?>" placeholder="Digite seu CPF" required autofocus maxlength="11" inputmode="numeric" pattern="\d{11}" data-only-digits>
                    </span>
                </label>

                <label class="auth-field">
                    <span>Senha</span>
                    <span class="auth-input-shell">
                        <span class="auth-input-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" focusable="false">
                                <path d="M17 9h-1V7a4 4 0 0 0-8 0v2H7a2 2 0 0 0-2 2v8a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2v-8a2 2 0 0 0-2-2Zm-6 0V7a2 2 0 1 1 4 0v2Z"></path>
                            </svg>
                        </span>
                        <input type="password" name="password" placeholder="Digite sua senha" required>
                    </span>
                </label>

                <button type="submit" class="primary-button auth-submit-button">
                    <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                        <path d="M10.5 3.75H6.75A2.25 2.25 0 0 0 4.5 6v12a2.25 2.25 0 0 0 2.25 2.25h3.75"></path>
                        <path d="M15 8.25 19.5 12 15 15.75"></path>
                        <path d="M19.5 12H9"></path>
                    </svg>
                    <span>Entrar</span>
                </button>

                <?php if (($flashes ?? []) !== []): ?>
                    <div class="auth-inline-flash-stack">
                        <?php foreach ($flashes as $type => $message): ?>
                            <div class="flash flash-<?= e($type) ?> auth-inline-flash"><?= e($message) ?></div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </form>

            <p class="auth-login-footer">Acesso restrito a usu&aacute;rios autorizados.</p>
        </div>
    </section>
</section>
