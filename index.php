<?php
$siteTitle = 'HelpDesk Facil - Sistema para assistencias tecnicas';
$siteDescription = 'Controle OS, clientes, produtos, servicos, financeiro e relatorios em um SaaS para assistencias tecnicas.';
$whatsappUrl = 'https://wa.me/5500000000000?text=Quero%20conhecer%20o%20HelpDesk%20Facil';

include __DIR__.'/_site_start.php';
?>
    <section class="hf-hero">
      <div class="container">
        <div class="hf-hero-grid">
          <div>
            <span class="hf-section-kicker">
              <i class="bi bi-tools" aria-hidden="true"></i>
              SaaS para assistencia tecnica
            </span>

            <h1 class="hf-hero-title">Gest&atilde;o completa para assist&ecirc;ncia t&eacute;cnica.</h1>

            <p class="hf-hero-text">
              Controle OS, clientes, produtos, servi&ccedil;os, financeiro e relat&oacute;rios em um painel online feito para organizar a rotina da sua equipe.
            </p>

            <div class="hf-hero-actions">
              <a class="btn btn-primary hf-btn-primary" href="/cadastro.php">
                Come&ccedil;ar teste gr&aacute;tis
                <i class="bi bi-arrow-right-short" aria-hidden="true"></i>
              </a>
              <a class="btn hf-btn-secondary" href="/planos.php">
                <i class="bi bi-grid-3x3-gap" aria-hidden="true"></i>
                Ver planos
              </a>
              <a class="btn hf-btn-whatsapp" href="<?= htmlspecialchars($whatsappUrl, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener">
                <i class="bi bi-whatsapp" aria-hidden="true"></i>
                Falar no WhatsApp
              </a>
            </div>

            <div class="hf-hero-proof" aria-label="Destaques do produto">
              <span><i class="bi bi-check-circle-fill" aria-hidden="true"></i> OS sem planilhas soltas</span>
              <span><i class="bi bi-check-circle-fill" aria-hidden="true"></i> Dados separados por empresa</span>
              <span><i class="bi bi-check-circle-fill" aria-hidden="true"></i> Relatorios para decisao</span>
            </div>
          </div>

          <div class="hf-hero-visual" aria-label="Previa visual do painel HelpDesk Facil">
            <div class="hf-hero-visual-bar">
              <div class="hf-window-controls">
                <span class="hf-hero-dot"></span>
                <span class="hf-hero-dot"></span>
                <span class="hf-hero-dot"></span>
              </div>
              <div class="hf-app-pill">
                <i class="bi bi-shield-check" aria-hidden="true"></i>
                assistencia-pro.helpdeskfacil.com
              </div>
              <span class="hf-live-badge">Ao vivo</span>
            </div>

            <div class="hf-hero-visual-body">
              <div class="hf-product-shell">
                <aside class="hf-product-sidebar" aria-hidden="true">
                  <div class="hf-product-mark"></div>
                  <span class="is-active"><i class="bi bi-speedometer2"></i></span>
                  <span><i class="bi bi-clipboard2-check"></i></span>
                  <span><i class="bi bi-people"></i></span>
                  <span><i class="bi bi-cash-coin"></i></span>
                  <span><i class="bi bi-graph-up"></i></span>
                </aside>

                <div class="hf-product-main">
                  <div class="hf-product-head">
                    <div>
                      <p>Painel operacional</p>
                      <h2>Resumo da assistencia</h2>
                    </div>
                    <span class="hf-status-pill"><i class="bi bi-stars" aria-hidden="true"></i> Plano Profissional</span>
                  </div>

                  <div class="hf-metric-grid">
                    <div class="hf-metric-card metric-blue">
                      <span><i class="bi bi-clipboard-check" aria-hidden="true"></i> OS abertas</span>
                      <strong>24</strong>
                      <small>+18% na semana</small>
                    </div>
                    <div class="hf-metric-card metric-green">
                      <span><i class="bi bi-wallet2" aria-hidden="true"></i> A receber</span>
                      <strong>R$ 8,4k</strong>
                      <small>12 servicos faturados</small>
                    </div>
                    <div class="hf-metric-card metric-purple">
                      <span><i class="bi bi-people" aria-hidden="true"></i> Clientes</span>
                      <strong>186</strong>
                      <small>Base ativa</small>
                    </div>
                  </div>

                  <div class="hf-product-grid">
                    <section class="hf-panel-card hf-os-panel">
                      <div class="hf-panel-head">
                        <h3>Ordens recentes</h3>
                        <span>Hoje</span>
                      </div>

                      <div class="hf-os-list">
                        <div class="hf-os-row">
                          <span class="hf-os-icon bg-blue"><i class="bi bi-laptop"></i></span>
                          <div>
                            <strong>#1028 Notebook Dell</strong>
                            <small>Aguardando diagnostico</small>
                          </div>
                          <span class="hf-tag tag-warning">Analise</span>
                        </div>
                        <div class="hf-os-row">
                          <span class="hf-os-icon bg-green"><i class="bi bi-printer"></i></span>
                          <div>
                            <strong>#1029 Impressora HP</strong>
                            <small>Servico finalizado</small>
                          </div>
                          <span class="hf-tag tag-success">Concluida</span>
                        </div>
                        <div class="hf-os-row">
                          <span class="hf-os-icon bg-purple"><i class="bi bi-phone"></i></span>
                          <div>
                            <strong>#1030 Celular Samsung</strong>
                            <small>Entrada registrada</small>
                          </div>
                          <span class="hf-tag tag-primary">Aberta</span>
                        </div>
                      </div>
                    </section>

                    <section class="hf-panel-card hf-chart-panel">
                      <div class="hf-panel-head">
                        <h3>Financeiro</h3>
                        <span>Mes atual</span>
                      </div>

                      <div class="hf-mini-chart" aria-hidden="true">
                        <span style="height: 42%"></span>
                        <span style="height: 64%"></span>
                        <span style="height: 52%"></span>
                        <span style="height: 76%"></span>
                        <span style="height: 88%"></span>
                        <span style="height: 70%"></span>
                      </div>

                      <div class="hf-chart-total">
                        <span>Receita prevista</span>
                        <strong>R$ 12.870</strong>
                      </div>
                    </section>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>

    <section class="hf-section" id="recursos">
      <div class="container">
        <span class="hf-section-kicker">
          <i class="bi bi-stars" aria-hidden="true"></i>
          Beneficios
        </span>
        <h2 class="hf-section-title">Mais clareza para a rotina da sua assist&ecirc;ncia.</h2>
        <p class="hf-section-lead">Centralize o que hoje fica espalhado entre papel, planilhas e conversas, com uma experiencia simples para a equipe inteira.</p>

        <div class="hf-feature-grid">
          <article class="hf-card">
            <div class="hf-card-body">
              <span class="hf-card-icon"><i class="bi bi-clipboard-check" aria-hidden="true"></i></span>
              <h3 class="hf-card-title">Nunca mais perca uma OS</h3>
              <p class="hf-card-text">Acompanhe cada atendimento desde a abertura ate a conclusao, com historico e status visiveis.</p>
            </div>
          </article>

          <article class="hf-card">
            <div class="hf-card-body">
              <span class="hf-card-icon"><i class="bi bi-people" aria-hidden="true"></i></span>
              <h3 class="hf-card-title">Clientes, produtos e servicos centralizados</h3>
              <p class="hf-card-text">Tenha cadastros organizados para acelerar novos atendimentos e manter o historico da operacao.</p>
            </div>
          </article>

          <article class="hf-card">
            <div class="hf-card-body">
              <span class="hf-card-icon"><i class="bi bi-cash-coin" aria-hidden="true"></i></span>
              <h3 class="hf-card-title">Financeiro integrado</h3>
              <p class="hf-card-text">Controle lancamentos e valores relacionados aos servicos sem depender de controles paralelos.</p>
            </div>
          </article>

          <article class="hf-card">
            <div class="hf-card-body">
              <span class="hf-card-icon"><i class="bi bi-bar-chart-line" aria-hidden="true"></i></span>
              <h3 class="hf-card-title">Relatorios para decisao</h3>
              <p class="hf-card-text">Visualize OS, clientes e financeiro com filtros e indicadores para decidir melhor.</p>
            </div>
          </article>

          <article class="hf-card">
            <div class="hf-card-body">
              <span class="hf-card-icon"><i class="bi bi-person-check" aria-hidden="true"></i></span>
              <h3 class="hf-card-title">Multiusuario</h3>
              <p class="hf-card-text">Permita que sua equipe trabalhe no mesmo ambiente, com acesso seguro por empresa.</p>
            </div>
          </article>

          <article class="hf-card">
            <div class="hf-card-body">
              <span class="hf-card-icon"><i class="bi bi-palette" aria-hidden="true"></i></span>
              <h3 class="hf-card-title">Sua marca no sistema</h3>
              <p class="hf-card-text">Use logo, cores e dados da empresa para entregar uma experiencia mais profissional.</p>
            </div>
          </article>
        </div>
      </div>
    </section>

    <section class="hf-section">
      <div class="container">
        <span class="hf-section-kicker">
          <i class="bi bi-grid" aria-hidden="true"></i>
          Modulos
        </span>
        <h2 class="hf-section-title">Tudo que a opera&ccedil;&atilde;o precisa no mesmo fluxo.</h2>
        <p class="hf-section-lead">Do atendimento ao recebimento, os principais modulos trabalham juntos para reduzir retrabalho e dar visibilidade ao negocio.</p>

        <div class="hf-module-grid">
          <article class="hf-card">
            <div class="hf-card-body">
              <span class="hf-card-icon"><i class="bi bi-wrench-adjustable" aria-hidden="true"></i></span>
              <h3 class="hf-card-title">Ordens de Servi&ccedil;o</h3>
              <p class="hf-card-text">Cadastre, acompanhe e finalize atendimentos com organizacao.</p>
            </div>
          </article>

          <article class="hf-card">
            <div class="hf-card-body">
              <span class="hf-card-icon"><i class="bi bi-person-vcard" aria-hidden="true"></i></span>
              <h3 class="hf-card-title">Clientes</h3>
              <p class="hf-card-text">Mantenha dados e historico do cliente sempre acessiveis.</p>
            </div>
          </article>

          <article class="hf-card">
            <div class="hf-card-body">
              <span class="hf-card-icon"><i class="bi bi-box-seam" aria-hidden="true"></i></span>
              <h3 class="hf-card-title">Produtos</h3>
              <p class="hf-card-text">Organize itens, pecas e produtos usados nos atendimentos.</p>
            </div>
          </article>

          <article class="hf-card">
            <div class="hf-card-body">
              <span class="hf-card-icon"><i class="bi bi-tools" aria-hidden="true"></i></span>
              <h3 class="hf-card-title">Servi&ccedil;os</h3>
              <p class="hf-card-text">Padronize servicos prestados e agilize o lancamento da OS.</p>
            </div>
          </article>

          <article class="hf-card">
            <div class="hf-card-body">
              <span class="hf-card-icon"><i class="bi bi-wallet2" aria-hidden="true"></i></span>
              <h3 class="hf-card-title">Financeiro</h3>
              <p class="hf-card-text">Acompanhe lancamentos, recebimentos e valores da operacao.</p>
            </div>
          </article>

          <article class="hf-card">
            <div class="hf-card-body">
              <span class="hf-card-icon"><i class="bi bi-graph-up-arrow" aria-hidden="true"></i></span>
              <h3 class="hf-card-title">Relat&oacute;rios</h3>
              <p class="hf-card-text">Enxergue resultados e acompanhe a evolucao da assistencia.</p>
            </div>
          </article>
        </div>
      </div>
    </section>

    <section class="hf-section" id="planos">
      <div class="container">
        <div class="d-flex flex-column flex-lg-row align-items-lg-end justify-content-between gap-3">
          <div>
            <span class="hf-section-kicker">
              <i class="bi bi-layers" aria-hidden="true"></i>
              Planos
            </span>
            <h2 class="hf-section-title">Comece simples e evolua conforme sua equipe cresce.</h2>
          </div>
          <a class="btn hf-btn-secondary" href="/planos.php">Comparar todos os planos</a>
        </div>

        <div class="hf-plan-grid">
          <article class="hf-plan-card">
            <h3 class="hf-plan-name">B&aacute;sico</h3>
            <p class="hf-plan-description">Para pequenas assistencias que querem sair do improviso e organizar as OS.</p>
            <ul class="hf-plan-list">
              <li><i class="bi bi-check-circle-fill" aria-hidden="true"></i> Ate 2 usuarios</li>
              <li><i class="bi bi-check-circle-fill" aria-hidden="true"></i> OS e clientes</li>
              <li><i class="bi bi-check-circle-fill" aria-hidden="true"></i> Financeiro simples</li>
            </ul>
            <a class="btn hf-btn-secondary w-100" href="/cadastro.php?plano=basico">Testar B&aacute;sico</a>
          </article>

          <article class="hf-plan-card is-featured">
            <span class="hf-plan-badge">Recomendado</span>
            <h3 class="hf-plan-name">Profissional</h3>
            <p class="hf-plan-description">Para equipes que precisam controlar atendimento, financeiro e relatorios no dia a dia.</p>
            <ul class="hf-plan-list">
              <li><i class="bi bi-check-circle-fill" aria-hidden="true"></i> Ate 5 usuarios</li>
              <li><i class="bi bi-check-circle-fill" aria-hidden="true"></i> Financeiro completo</li>
              <li><i class="bi bi-check-circle-fill" aria-hidden="true"></i> Relatorios e branding</li>
            </ul>
            <a class="btn btn-primary hf-btn-primary w-100" href="/cadastro.php?plano=profissional">Testar Profissional</a>
          </article>

          <article class="hf-plan-card">
            <h3 class="hf-plan-name">Premium</h3>
            <p class="hf-plan-description">Para empresas que querem mais usuarios, suporte prioritario e personalizacao.</p>
            <ul class="hf-plan-list">
              <li><i class="bi bi-check-circle-fill" aria-hidden="true"></i> Ate 15 usuarios</li>
              <li><i class="bi bi-check-circle-fill" aria-hidden="true"></i> Recursos avancados</li>
              <li><i class="bi bi-check-circle-fill" aria-hidden="true"></i> Suporte prioritario</li>
            </ul>
            <a class="btn hf-btn-secondary w-100" href="<?= htmlspecialchars($whatsappUrl, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener">Falar sobre Premium</a>
          </article>
        </div>
      </div>
    </section>

    <section class="hf-section-tight">
      <div class="container">
        <div class="hf-cta-band">
          <div class="row align-items-center g-4">
            <div class="col-lg-7">
              <span class="hf-section-kicker">Teste gratis</span>
              <h2 class="h1 fw-bold mb-3">Pronto para organizar sua assist&ecirc;ncia t&eacute;cnica?</h2>
              <p class="mb-0 text-secondary fs-5">Comece com uma estrutura online para controlar atendimentos, clientes, financeiro e relatorios com mais profissionalismo.</p>
            </div>
            <div class="col-lg-5">
              <div class="d-grid d-sm-flex justify-content-lg-end gap-2">
                <a class="btn btn-primary hf-btn-primary" href="/cadastro.php">Come&ccedil;ar teste gr&aacute;tis</a>
                <a class="btn hf-btn-secondary" href="<?= htmlspecialchars($whatsappUrl, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener">
                  <i class="bi bi-whatsapp" aria-hidden="true"></i>
                  Falar no WhatsApp
                </a>
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>

    <section class="hf-section" id="faq">
      <div class="container">
        <span class="hf-section-kicker">
          <i class="bi bi-question-circle" aria-hidden="true"></i>
          FAQ
        </span>
        <h2 class="hf-section-title">Perguntas frequentes.</h2>

        <div class="accordion hf-faq mt-4" id="hfFaq">
          <div class="accordion-item">
            <h3 class="accordion-header">
              <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#faqOnline" aria-expanded="true" aria-controls="faqOnline">O sistema e online?</button>
            </h3>
            <div id="faqOnline" class="accordion-collapse collapse show" data-bs-parent="#hfFaq">
              <div class="accordion-body">Sim. O HelpDesk Facil e acessado pelo navegador, com login por empresa para manter a operacao organizada.</div>
            </div>
          </div>

          <div class="accordion-item">
            <h3 class="accordion-header">
              <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faqInstalar" aria-expanded="false" aria-controls="faqInstalar">Precisa instalar alguma coisa?</button>
            </h3>
            <div id="faqInstalar" class="accordion-collapse collapse" data-bs-parent="#hfFaq">
              <div class="accordion-body">Nao. A proposta do SaaS e funcionar online, sem instalacao local para a equipe usar no dia a dia.</div>
            </div>
          </div>

          <div class="accordion-item">
            <h3 class="accordion-header">
              <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faqUsuarios" aria-expanded="false" aria-controls="faqUsuarios">Posso cadastrar usuarios da equipe?</button>
            </h3>
            <div id="faqUsuarios" class="accordion-collapse collapse" data-bs-parent="#hfFaq">
              <div class="accordion-body">Sim. A empresa pode trabalhar com usuarios internos, respeitando os limites e recursos do plano contratado.</div>
            </div>
          </div>

          <div class="accordion-item">
            <h3 class="accordion-header">
              <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faqEmpresa" aria-expanded="false" aria-controls="faqEmpresa">Os dados ficam separados por empresa?</button>
            </h3>
            <div id="faqEmpresa" class="accordion-collapse collapse" data-bs-parent="#hfFaq">
              <div class="accordion-body">Sim. O sistema trabalha com estrutura multi-tenant, mantendo cada empresa em seu proprio escopo de acesso.</div>
            </div>
          </div>

          <div class="accordion-item">
            <h3 class="accordion-header">
              <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faqMarca" aria-expanded="false" aria-controls="faqMarca">Posso usar minha logo e minhas cores?</button>
            </h3>
            <div id="faqMarca" class="accordion-collapse collapse" data-bs-parent="#hfFaq">
              <div class="accordion-body">Sim. O HelpDesk Facil ja possui recursos de branding para deixar o painel com a identidade da empresa.</div>
            </div>
          </div>

          <div class="accordion-item">
            <h3 class="accordion-header">
              <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faqFinanceiro" aria-expanded="false" aria-controls="faqFinanceiro">O sistema tem financeiro?</button>
            </h3>
            <div id="faqFinanceiro" class="accordion-collapse collapse" data-bs-parent="#hfFaq">
              <div class="accordion-body">Sim. A area financeira ajuda a acompanhar lancamentos e valores relacionados a operacao da assistencia.</div>
            </div>
          </div>

          <div class="accordion-item">
            <h3 class="accordion-header">
              <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faqTrial" aria-expanded="false" aria-controls="faqTrial">Existe teste gratis?</button>
            </h3>
            <div id="faqTrial" class="accordion-collapse collapse" data-bs-parent="#hfFaq">
              <div class="accordion-body">Sim. O fluxo comercial sera preparado para permitir cadastro de teste gratis antes da contratacao.</div>
            </div>
          </div>

          <div class="accordion-item">
            <h3 class="accordion-header">
              <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faqPlano" aria-expanded="false" aria-controls="faqPlano">Posso mudar de plano depois?</button>
            </h3>
            <div id="faqPlano" class="accordion-collapse collapse" data-bs-parent="#hfFaq">
              <div class="accordion-body">Sim. A estrutura comercial deve permitir evoluir de plano conforme a empresa cresce e precisa de mais recursos.</div>
            </div>
          </div>
        </div>
      </div>
    </section>
    <a class="hf-floating-whatsapp" href="<?= htmlspecialchars($whatsappUrl, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener" aria-label="Falar no WhatsApp">
      <i class="bi bi-whatsapp" aria-hidden="true"></i>
      <span>WhatsApp</span>
    </a>
<?php include __DIR__.'/_site_end.php'; ?>
