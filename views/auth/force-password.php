<section class="auth-split">
    <div class="auth-split-topbar">
        <?= view('auth/_theme_switch') ?>
    </div>

    <?= view('auth/_visual') ?>

    <section class="auth-form-side">
        <div class="auth-login-card auth-login-card-first-access">
            <div class="auth-login-head">
                <p class="eyebrow auth-login-eyebrow">
                    <span class="auth-login-eyebrow-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24" focusable="false">
                            <path d="M17 9h-1V7a4 4 0 0 0-8 0v2H7a2 2 0 0 0-2 2v8a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2v-8a2 2 0 0 0-2-2Zm-6 0V7a2 2 0 1 1 4 0v2Z"></path>
                        </svg>
                    </span>
                    <span>Primeiro acesso</span>
                </p>
                <h2>Crie sua nova senha</h2>
                <p>Identificamos que esta &eacute; sua senha inicial. Atualize agora para continuar com seguran&ccedil;a.</p>
            </div>

            <form method="post" class="auth-login-form" data-first-access-form novalidate>
                <?= \App\Core\Csrf::input() ?>

                <label class="auth-field">
                    <span>Nova senha</span>
                    <span class="auth-input-shell">
                        <span class="auth-input-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" focusable="false">
                                <path d="M17 9h-1V7a4 4 0 0 0-8 0v2H7a2 2 0 0 0-2 2v8a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2v-8a2 2 0 0 0-2-2Zm-6 0V7a2 2 0 1 1 4 0v2Z"></path>
                            </svg>
                        </span>
                        <input type="password" name="new_password" placeholder="Crie uma senha forte" required autocomplete="new-password">
                    </span>
                </label>

                <label class="auth-field">
                    <span>Confirmar senha</span>
                    <span class="auth-input-shell">
                        <span class="auth-input-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" focusable="false">
                                <path d="M17 9h-1V7a4 4 0 0 0-8 0v2H7a2 2 0 0 0-2 2v8a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2v-8a2 2 0 0 0-2-2Zm-6 0V7a2 2 0 1 1 4 0v2Z"></path>
                            </svg>
                        </span>
                        <input type="password" name="new_password_confirmation" placeholder="Repita a nova senha" required autocomplete="new-password">
                    </span>
                </label>

                <div class="auth-password-rules">
                    <p class="auth-password-rules-title">Sua nova senha precisa ter:</p>

                    <div class="auth-password-rule" data-password-rule="length">
                        <span class="auth-password-rule-mark" aria-hidden="true">
                            <svg viewBox="0 0 20 20" focusable="false">
                                <path d="m5 10 3.1 3.2L15 6.6"></path>
                            </svg>
                        </span>
                        <span>M&iacute;nimo de 6 caracteres</span>
                    </div>

                    <div class="auth-password-rule" data-password-rule="uppercase">
                        <span class="auth-password-rule-mark" aria-hidden="true">
                            <svg viewBox="0 0 20 20" focusable="false">
                                <path d="m5 10 3.1 3.2L15 6.6"></path>
                            </svg>
                        </span>
                        <span>1 letra mai&uacute;scula</span>
                    </div>

                    <div class="auth-password-rule" data-password-rule="special">
                        <span class="auth-password-rule-mark" aria-hidden="true">
                            <svg viewBox="0 0 20 20" focusable="false">
                                <path d="m5 10 3.1 3.2L15 6.6"></path>
                            </svg>
                        </span>
                        <span>1 caractere especial</span>
                    </div>

                    <div class="auth-password-rule" data-password-rule="match">
                        <span class="auth-password-rule-mark" aria-hidden="true">
                            <svg viewBox="0 0 20 20" focusable="false">
                                <path d="m5 10 3.1 3.2L15 6.6"></path>
                            </svg>
                        </span>
                        <span>Confirma&ccedil;&atilde;o igual &agrave; senha</span>
                    </div>
                </div>

                <button type="submit" class="primary-button auth-submit-button">
                    <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                        <path d="M10.5 3.75H6.75A2.25 2.25 0 0 0 4.5 6v12a2.25 2.25 0 0 0 2.25 2.25h3.75"></path>
                        <path d="M15 8.25 19.5 12 15 15.75"></path>
                        <path d="M19.5 12H9"></path>
                    </svg>
                    <span>Alterar senha</span>
                </button>
            </form>

            <p class="auth-login-footer">Depois da altera&ccedil;&atilde;o, voc&ecirc; vai voltar ao login para entrar com a nova senha.</p>
        </div>
    </section>
</section>
