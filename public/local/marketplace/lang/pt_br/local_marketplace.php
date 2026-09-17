<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Strings do local_marketplace.
 *
 * @package    local_marketplace
 * @copyright  2026 LeoDG <callme@leodg.dev>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['accessdays'] = 'Acesso por {$a} dias';
$string['accessgranted'] = 'Acesso liberado. Bom curso!';
$string['accesslifetime'] = 'Acesso vitalício';
$string['accessrecurring'] = 'Assinatura, renovada a cada {$a} dias';
$string['accessrecurringlimited'] = 'Assinatura: {$a->billing} dias por pagamento, até {$a->cycles} pagamentos';
$string['accessrecurringopen'] = 'Assinatura: {$a->billing} dias por pagamento, sem prazo final';
$string['accessuntil'] = 'Acesso até {$a}';
$string['addmember'] = 'Adicionar vendedor';
$string['addmember_help'] = 'Vincula um usuário existente da plataforma a esta empresa e concede a ele o papel de vendedor na categoria dela. A pessoa precisa já ter conta.';
$string['addplan'] = 'Adicionar plano';
$string['alreadyowned'] = 'Você já tem acesso a esta oferta.';
$string['buynow'] = 'Comprar agora';
$string['cancelconfirm'] = 'Cancelar <strong>{$a->offer}</strong>? Você mantém o acesso até {$a->date} — pagou por esse período e ele não é retirado. Depois disso o acesso simplesmente termina, e paramos de lembrar você de renovar.';
$string['cancelconfirmlifetime'] = 'Parar os avisos de renovação de <strong>{$a}</strong>? Seu acesso não vence, então nada muda além dos avisos.';
$string['canceldone'] = 'Assinatura cancelada. Seu acesso vale até {$a}.';
$string['cancelledbut'] = 'Cancelada. Seu acesso vale até {$a} — o período que você pagou não é retirado.';
$string['cancelledlifetime'] = 'Avisos de renovação desligados. Seu acesso não vence.';
$string['cancelsubscription'] = 'Cancelar assinatura';
$string['cancelundo'] = 'Reativar';
$string['cancelundone'] = 'Assinatura reativada. Você será avisado antes do vencimento.';
$string['cannotsell'] = 'Somente cursos gratuitos';
$string['cansell'] = 'Vendendo';
$string['cansellyes'] = 'Pronta para vender. Gateway ativo: {$a}';
$string['commissionbase'] = 'Base da comissão';
$string['commissionbase_desc'] = 'Sobre o que o percentual de comissão incide, para o plano ou a empresa que não declarar a própria base. <b>Bruto</b> significa que a plataforma recebe o percentual combinado sobre o preço da venda e o vendedor absorve a taxa do gateway. <b>Líquido</b> significa que a taxa do gateway sai primeiro e os dois lados a dividem.<br><br>Líquido não existe em todo gateway: o Mercado Pago cobra por valor absoluto e a taxa dele só é conhecida depois, então as vendas por lá saem sempre sobre o bruto. Cada venda registra a base que foi de fato aplicada.';
$string['commissionbasegross'] = 'sobre o bruto';
$string['commissionbaseinherit'] = 'Herdar a base do site';
$string['commissionbasenet'] = 'sobre o líquido';
$string['commissioneffective'] = 'Comissão efetiva';
$string['commissionfromcompany'] = 'negociada com a empresa';
$string['commissionfromplan'] = 'do plano {$a}';
$string['commissionfromsite'] = 'padrão do site';
$string['commissionpct'] = 'Comissão da plataforma (%)';
$string['commissionsourcecompany'] = 'negociada';
$string['commissionsourceplan'] = 'plano';
$string['commissionsourcepolicy'] = 'política do curso';
$string['commissionsourcesite'] = 'padrão do site';
$string['companies'] = 'Empresas';
$string['company'] = 'Empresa';
$string['companycnpj'] = 'CNPJ';
$string['companycnpj_help'] = 'Opcional. Pessoa física pode vender sem CNPJ.';
$string['companycommission'] = 'Comissão (%)';
$string['companycommission_help'] = 'Percentual que a plataforma retém nas vendas desta empresa, de 0 a 100. Deixe vazio para usar o padrão do site — vazio e zero são diferentes: vazio significa que nada foi negociado, zero significa que o parceiro é isento.';
$string['companycommissionbase'] = 'Base da comissão';
$string['companycommissionbase_help'] = 'Só é lida quando há comissão negociada preenchida acima. Deixe herdando para a empresa acompanhar a política do site.';
$string['companycreated'] = 'Empresa {$a} criada. Adicione os outros vendedores abaixo.';
$string['companyhostname'] = 'Domínio próprio';
$string['companyhostname_help'] = 'Opcional. O domínio próprio da empresa, sem o esquema, por exemplo <b>cursos.parceiro.com</b>. Ele precisa apontar para este servidor, e o certificado é responsabilidade de quem opera o DNS. Deixar vazio mantém a empresa no domínio da plataforma.';
$string['companyname'] = 'Nome';
$string['companyowner'] = 'Responsável';
$string['companyowner_help'] = 'Quem administra a empresa e vincula a conta Mercado Pago dela. Precisa já ter conta na plataforma.';
$string['companypanel'] = 'Marketplace: painel da empresa';
$string['companyshortname'] = 'Nome curto';
$string['companyshortname_help'] = 'Usado na URL da empresa. Apenas letras, números e hifens.';
$string['companystatus'] = 'Situação';
$string['companytheme'] = 'Tema';
$string['companyupdated'] = 'Empresa {$a} atualizada.';
$string['configurepayment'] = 'Configurar Mercado Pago';
$string['createcompany'] = 'Criar empresa';
$string['createcompanyintro'] = 'Criar uma empresa provisiona uma categoria de cursos, atribui o papel de vendedor ao responsável nessa categoria e cria uma conta de pagamento. Feche a parceria antes — esta tela apenas executa.';
$string['currentplan'] = 'Plano atual: {$a}';
$string['defaultcountry'] = 'País padrão';
$string['defaultcountry_desc'] = 'País em que a conta de pagamento de uma empresa nova é provisionada. Não é um limite: a empresa pode receber contas em outros países depois, e é a oferta que diz onde cada plano vende.';
$string['defaultfeepercent'] = 'Comissão padrão (%)';
$string['defaultfeepercent_desc'] = 'Percentual que a plataforma retém quando nem o curso nem a empresa têm taxa negociada. Uma empresa em 0% continua em 0%: campo vazio significa "herda este padrão", que não é a mesma coisa que "sem comissão".';
$string['defaultthemename'] = 'Tema padrão do site';
$string['domainsuspendedbody'] = 'A empresa responsável por este endereço não está vendendo no momento. Se você já comprou um curso, continua podendo acessá-lo pela plataforma principal.';
$string['domainsuspendedtitle'] = 'Esta loja está indisponível';
$string['editcompanyintro'] = 'O nome curto não pode ser alterado: ele é o número de identificação da categoria e aparece em links da vitrine e do painel que o vendedor pode já ter divulgado. O responsável é trocado na tela de vendedores, onde você pode promover outra pessoa antes.';
$string['editplan'] = 'Editar plano';
$string['erroraccessdays'] = 'Informe ao menos um dia de acesso.';
$string['erroraccounttaken'] = 'Esta conta de pagamento já está vinculada a outra empresa ou a outro país.';
$string['erroralreadymember'] = 'Esta pessoa já é vendedora desta empresa.';
$string['errorbillingdays'] = 'Informe o intervalo de cobrança em dias.';
$string['errorcannotremoveowner'] = 'O responsável não pode ser removido. Promova outra pessoa antes — uma empresa sem responsável fica sem ninguém encarregado da conta de pagamento.';
$string['errorcannotsell'] = 'Esta empresa ainda não pode vender: configure um meio de pagamento primeiro.';
$string['errorcnpjinvalid'] = 'Este CNPJ não é válido.';
$string['errorcommissionrange'] = 'Use um número de 0 a 100, ou deixe vazio para herdar o padrão do site.';
$string['errorcountryunsupported'] = 'O marketplace não opera no país {$a}.';
$string['errordomainmap'] = 'Nao foi possivel gravar o mapa de dominios dos vendedores. Confira se o diretorio de dados do Moodle tem permissao de escrita.';
$string['errorhostnametaken'] = 'Este domínio já está vinculado a outra empresa.';
$string['errormaxcycles'] = 'Use zero para não haver limite, ou um número positivo.';
$string['errornoaccount'] = 'Esta empresa não tem conta de pagamento. Reinstale ou recrie a empresa.';
$string['errornocourses'] = 'Escolha ao menos um curso, ou use o tipo Catálogo completo.';
$string['errorpageaccent'] = 'Use uma cor hexadecimal como #B85410, ou deixe vazio.';
$string['errorplanarchived'] = 'Este plano está arquivado e não pode ser atribuído a uma empresa.';
$string['errorplanfeenegative'] = 'A mensalidade não pode ser negativa.';
$string['errorplannotbillable'] = 'Esta empresa não tem um plano com mensalidade para cobrar.';
$string['errorplannotfound'] = 'O plano selecionado não existe.';
$string['errorplanshortnametaken'] = 'Outro plano já usa este nome curto.';
$string['errorplantiernegative'] = 'O teto de preço não pode ser negativo.';
$string['errorplatformhostingunavailable'] = 'Hospedar vídeo na plataforma ainda não está disponível.';
$string['errorrecurringfree'] = 'Uma assinatura precisa de preço. Gratuita, ela venceria sem forma de renovar.';
$string['errorsellerrolemissing'] = 'O papel de vendedor não existe. Reinstale o plugin Marketplace.';
$string['errorshortnametaken'] = 'Este nome curto já está em uso.';
$string['errorsinglemanycourses'] = 'Uma oferta de curso único libera um curso. Use Combo para mais de um.';
$string['expiringbody'] = 'Olá,

Seu acesso a {$a->offer}, de {$a->company}, termina em {$a->date}.

Não há cobrança automática — para manter o acesso, pague novamente aqui:
{$a->url}

Se preferir parar, não faça nada e o acesso simplesmente termina.';
$string['expiringbodyhtml'] = '<p>Olá,</p><p>Seu acesso a <strong>{$a->offer}</strong>, de {$a->company}, termina em <strong>{$a->date}</strong>.</p><p>Não há cobrança automática — para manter o acesso, <a href="{$a->url}">pague novamente aqui</a>.</p><p>Se preferir parar, não faça nada e o acesso simplesmente termina.</p>';
$string['expiringlastbody'] = 'Olá,

Este é o último aviso: seu acesso a {$a->offer}, de {$a->company}, termina em {$a->date}.

Depois disso os cursos ficam bloqueados até você pagar de novo. Nada se perde — suas notas e seu progresso continuam, e o acesso volta assim que o pagamento entrar:
{$a->url}

Se preferir parar, não faça nada.';
$string['expiringlastbodyhtml'] = '<p>Olá,</p><p>Este é o <strong>último aviso</strong>: seu acesso a <strong>{$a->offer}</strong>, de {$a->company}, termina em <strong>{$a->date}</strong>.</p><p>Depois disso os cursos ficam bloqueados até você pagar de novo. Nada se perde — suas notas e seu progresso continuam, e o acesso volta assim que o pagamento entrar: <a href="{$a->url}">pagar agora</a>.</p><p>Se preferir parar, não faça nada.</p>';
$string['expiringlastsubject'] = 'Último aviso: {$a->offer} será bloqueado em {$a->days} dia(s)';
$string['expiringline'] = 'Linha digitável do boleto, para copiar: {$a}';
$string['expiringsubject'] = 'Seu acesso a {$a->offer} termina em {$a->days} dia(s)';
$string['filterallcategories'] = 'Todas as categorias';
$string['filteralltypes'] = 'Todos os tipos';
$string['filtercategory'] = 'Categoria';
$string['filterclear'] = 'Limpar filtros';
$string['filtertype'] = 'Tipo';
$string['free'] = 'Gratuito';
$string['getfree'] = 'Obter acesso gratuito';
$string['hostingbyos'] = 'BYOS: o produtor conecta o armazenamento dele';
$string['hostingexternal'] = 'Fora da plataforma';
$string['hostingnative'] = 'Nativa: a plataforma hospeda o vídeo';
$string['hostingplatform'] = 'Na plataforma';
$string['hostingtype'] = 'Hospedagem do vídeo';
$string['linkednotenabled'] = 'A conta Mercado Pago está vinculada, mas o gateway está desligado, então nada pode ser vendido ainda. Abra as configurações de pagamento e habilite.';
$string['makeowner'] = 'Tornar responsável';
$string['makeseller'] = 'Tornar vendedor';
$string['managecourses'] = 'Gerenciar cursos';
$string['managemembers'] = 'Vendedores';
$string['managerrole'] = 'Responsável pela empresa';
$string['managerroledesc'] = 'Monta cursos por uma empresa e responde pela conta de pagamento e pelos membros dela. Como o vendedor, não pode enviar arquivos: os vídeos do curso precisam ser hospedados fora da plataforma.';
$string['marketplace:createcompany'] = 'Criar uma empresa';
$string['marketplace:manageall'] = 'Administrar todas as empresas da plataforma';
$string['marketplace:managecompany'] = 'Administrar a empresa';
$string['marketplace:managepayment'] = 'Administrar a conta de pagamento da empresa';
$string['marketplace:managesales'] = 'Gerir as vendas e assinaturas da empresa';
$string['marketplace:publishcourse'] = 'Publicar curso pela empresa';
$string['marketplace:refundsale'] = 'Estornar uma venda, devolvendo o dinheiro e revogando o acesso';
$string['marketplace:viewreport'] = 'Ver o relatório financeiro da empresa';
$string['memberadded'] = 'Vendedor adicionado.';
$string['memberowner'] = 'Responsável';
$string['memberremoved'] = 'Vendedor removido.';
$string['memberrolechanged'] = 'Papel alterado.';
$string['members'] = 'Vendedores';
$string['memberseller'] = 'Vendedor';
$string['membersof'] = 'Vendedores de {$a}';
$string['messageprovider:expiring'] = 'Acesso prestes a vencer';
$string['modedays'] = 'Prazo fixo';
$string['modelifetime'] = 'Vitalício';
$string['moderecurring'] = 'Assinatura';
$string['mysubsactive'] = 'Assinaturas';
$string['mysubscriptions'] = 'Minhas assinaturas';
$string['mysubspayments'] = 'Pagamentos';
$string['nocompanies'] = 'Nenhuma empresa cadastrada.';
$string['nocompany'] = 'Você não pertence a nenhuma empresa.';
$string['nogatewayforcountry'] = 'Nenhum meio de pagamento instalado consegue receber neste país.';
$string['nomembers'] = 'Esta empresa não tem vendedores.';
$string['nooffers'] = 'Esta empresa ainda não tem ofertas publicadas.';
$string['nooffersfiltered'] = 'Nenhuma oferta corresponde a estes filtros.';
$string['nopaymentaccount'] = 'Esta empresa não tem meio de pagamento configurado, então só pode publicar cursos gratuitos.';
$string['nopayments'] = 'Nenhum pagamento ainda.';
$string['noplan'] = 'Sem plano';
$string['noplans'] = 'Ainda não há planos.';
$string['nosubscriptions'] = 'Você ainda não comprou nada.';
$string['offeraccess'] = 'Acesso e cobrança';
$string['offeraccessdays'] = 'Dias de acesso por pagamento';
$string['offeraccessdays_help'] = 'Quanto acesso cada pagamento libera. Numa assinatura, pode ser maior que o intervalo de cobrança para dar carência: cobrar a cada 30 dias liberando 35 deixa um pagamento atrasado passar sem cortar o aluno.';
$string['offeraccessmode'] = 'Modelo de acesso';
$string['offeraccessmode_help'] = 'Vitalício nunca vence. Prazo fixo dá um número de dias por compra. Assinatura dá um período e espera renovação.';
$string['offerbillingdays'] = 'Intervalo de cobrança (dias)';
$string['offerbillingdays_help'] = 'De quanto em quanto tempo se espera o próximo pagamento. Usado no aviso de vencimento.';
$string['offercountry'] = 'País';
$string['offercountry_help'] = 'Onde esta oferta vende. Decide a conta que recebe o dinheiro, a moeda e quais meios de pagamento aparecem no checkout. Vender em outro país é outra oferta, e não outro preço nesta: o subsistema de pagamento resolve valor, moeda e conta a partir da oferta, sem saber quem está comprando.';
$string['offercourses'] = 'Cursos liberados';
$string['offercourses_help'] = 'Quais cursos esta oferta libera. Não é necessário no Catálogo completo, que segue a categoria da empresa.';
$string['offercreate'] = 'Nova oferta';
$string['offeredit'] = 'Oferta';
$string['offerincludes'] = 'Inclui {$a} curso(s)';
$string['offermaxcycles'] = 'Máximo de pagamentos';
$string['offermaxcycles_help'] = 'Quantas vezes esta assinatura pode ser cobrada no total. Zero significa sem limite. Use 12 para um plano mensal que dura um ano, ou 3 para um plano anual que dura três anos.';
$string['offername'] = 'Oferta';
$string['offerprice'] = 'Preço';
$string['offerprice_help'] = 'Zero torna a oferta gratuita. Ofertas gratuitas não passam pelo Mercado Pago.';
$string['offerpublication'] = 'Publicação';
$string['offerrecurringwarning'] = 'O Mercado Pago não tem cobrança recorrente com split, então nada é debitado automaticamente. O aluno recebe um aviso antes do vencimento, com link para pagar de novo.';
$string['offersaved'] = 'Oferta salva.';
$string['offersortorder'] = 'Ordem de exibição';
$string['offerssection'] = 'Ofertas';
$string['offerstatus_help'] = 'Somente ofertas publicadas aparecem na vitrine. Arquivar não revoga acesso já comprado.';
$string['offertype'] = 'Tipo';
$string['offertype_help'] = 'Curso único vende um curso. Combo vende um conjunto escolhido — é assim que se montam planos em níveis como Básico, Intermediário e Completo para a mesma empresa. Catálogo completo segue a categoria da empresa, então cursos novos entram sozinhos.';
$string['offerunlocks'] = 'Esta é a oferta que libera o conteúdo que você estava vendo.';
$string['pageaccent'] = 'Cor de destaque';
$string['pageaccent_help'] = 'Hexadecimal, tipo #B85410. Colore os botões de compra e fica disponível ao seu CSS na variável --mp-accent.';
$string['pagecss'] = 'Folha de estilo própria';
$string['pagecss_help'] = 'Um arquivo .css carregado depois do tema, então consegue sobrescrevê-lo. Servido como folha de estilo, nunca embutido — o navegador jamais o trata como script. Para uma página totalmente própria, construa onde quiser e leia as ofertas pela API.';
$string['pageintro'] = 'Texto de abertura';
$string['pageintro_help'] = 'Aparece acima das ofertas. Escrito como texto formatado e filtrado pelo Moodle — é texto de venda, não lugar para script.';
$string['pagelogo'] = 'Logo da marca';
$string['pagelogo_help'] = 'Aparece no topo da sua vitrine. Uma imagem web — PNG ou SVG com fundo transparente funciona melhor. É exibida com até 96px de altura.';
$string['pagesection'] = 'Vitrine';
$string['pagetitle'] = 'Título da vitrine';
$string['pagetitle_help'] = 'Aparece como título da página. Vazio usa o nome da empresa.';
$string['payinvoice'] = 'Pagar este ciclo';
$string['paymentsection'] = 'Meio de pagamento';
$string['paysubscription'] = 'Pagar assinatura';
$string['plan'] = 'Plano';
$string['plancommissionbase'] = 'Base da comissão';
$string['plancommissionbase_help'] = 'Sobre o que o percentual deste plano incide. Deixe herdando, a não ser que o plano venda um arranjo diferente do resto da plataforma. A base faz parte do que o parceiro contratou, e mudá-la depois não afeta as vendas já feitas.';
$string['plancommissionpct'] = 'Comissão (%)';
$string['plancommissionpct_help'] = 'Vale para as empresas neste plano que não têm comissão negociada individualmente. Um valor negociado na empresa sempre vence o do plano.';
$string['plancountry'] = 'País';
$string['plandescription'] = 'Descrição';
$string['planexpiryon'] = 'Assinatura paga até {$a}';
$string['planhostingmodel'] = 'Modelo de hospedagem';
$string['planispublic'] = 'Exibir na comparação pública de planos';
$string['planmonthlyfee'] = 'Mensalidade';
$string['planmonthlyfee_help'] = 'Cobrada automaticamente, a cada 30 dias, pelo painel da empresa — veja "Pagar assinatura" na página da empresa.';
$string['planname'] = 'Nome';
$string['plannotpaidyet'] = 'Ainda não paga.';
$string['planprodesc'] = 'Para quem já vende, traz o próprio armazenamento e paga menos comissão.';
$string['planproname'] = 'Pro';
$string['plans'] = 'Planos';
$string['planscaledesc'] = 'Para operação em escala: sem comissão, armazenamento próprio e suporte prioritário.';
$string['planscalename'] = 'Scale';
$string['planshortname'] = 'Nome curto';
$string['planshortname_help'] = 'Chave estável usada em código e pelo seed da instalação. Ao contrário do nome, não é para mudar com o marketing.';
$string['plansortorder'] = 'Ordem de exibição';
$string['planssection'] = 'Plano';
$string['planstart100desc'] = 'Hospedagem na plataforma, qualidade máxima de vídeo (4K), 10% de comissão por venda.';
$string['planstart100name'] = 'Start — R$100/mês';
$string['planstart50desc'] = 'Hospedagem na plataforma, qualidade de vídeo maior (1080p), 10% de comissão por venda.';
$string['planstart50name'] = 'Start — R$50/mês';
$string['planstarterdesc'] = 'Mensalidade zero: você só paga quando vende. Hospedagem de vídeo inclusa, com teto de resolução conforme o preço do curso.';
$string['planstartername'] = 'Starter';
$string['planstartfreedesc'] = 'Mensalidade zero, hospedagem na plataforma em 720p, 10% de comissão por venda. A porta de entrada — dá pra subir de tier a qualquer momento para melhorar a qualidade do vídeo.';
$string['planstartfreename'] = 'Start — Grátis';
$string['planstatus'] = 'Situação';
$string['planstatusactive'] = 'Ativo';
$string['planstatusarchived'] = 'Arquivado';
$string['plantiermaxprice'] = 'Teto de preço';
$string['plantiermaxresolution'] = 'Resolução máxima';
$string['plantiernolimit'] = 'Sem teto';
$string['plantiers'] = 'Tetos de resolução por preço do curso';
$string['plantiers_help'] = 'Cada linha limita a resolução do vídeo para cursos até o preço informado. A última linha, com preço vazio, cobre tudo acima. Só faz sentido quando a banda é paga pela plataforma.';
$string['platformaccountname'] = 'Plataforma ({$a})';
$string['pluginname'] = 'Marketplace';
$string['privacy:metadata'] = 'O plugin Marketplace guarda empresas, seus vendedores e credenciais de pagamento.';
$string['privacy:metadata:entitlement'] = 'O que o aluno comprou e por quanto tempo vale o acesso dele.';
$string['privacy:metadata:entitlement:companyid'] = 'A empresa que vendeu.';
$string['privacy:metadata:entitlement:cycles'] = 'Quantos pagamentos já foram feitos.';
$string['privacy:metadata:entitlement:offerid'] = 'A oferta comprada.';
$string['privacy:metadata:entitlement:status'] = 'Se o acesso está vigente, vencido ou revogado.';
$string['privacy:metadata:entitlement:timeend'] = 'Quando o acesso termina. Zero significa que não vence.';
$string['privacy:metadata:entitlement:timestart'] = 'Quando o acesso começou.';
$string['privacy:metadata:entitlement:userid'] = 'O aluno.';
$string['privacy:metadata:member'] = 'Por quais empresas a pessoa vende.';
$string['privacy:metadata:member:companyid'] = 'A empresa.';
$string['privacy:metadata:member:memberrole'] = 'Se ela responde pela empresa ou vende por ela.';
$string['privacy:metadata:member:timecreated'] = 'Quando o vínculo foi feito.';
$string['privacy:metadata:member:userid'] = 'A pessoa vinculada à empresa.';
$string['refundconfirm'] = 'Estornar {$a->amount} para {$a->user}, referente a {$a->offer}?<br><br>O dinheiro volta pelo gateway, a comissão é cancelada junto, e o aluno <strong>perde o acesso imediatamente</strong>. Se esta for a primeira cobrança de uma assinatura, a assinatura também é cancelada. Não há como desfazer.';
$string['refunddone'] = 'Venda estornada e acesso revogado.';
$string['refundfailed'] = 'O gateway não aceitou o estorno. Nada foi alterado aqui.';
$string['refundsale'] = 'Estornar';
$string['renewnotice'] = 'Seu acesso termina em {$a}. Renove para mantê-lo.';
$string['renewnow'] = 'Renovar agora';
$string['reportaccessuntil'] = 'Acesso até';
$string['reportall'] = 'Todo o período';
$string['reportcommission'] = 'Comissão da plataforma';
$string['reportcommissionterms'] = 'Termos aplicados';
$string['reportcoursesnotice'] = 'Um combo conta inteiro para cada curso que libera — ninguém compra um terço de um combo. Então esta coluna soma mais que seu faturamento total, e serve para comparar cursos entre si, não para somar.';
$string['reportdays'] = 'Últimos {$a} dias';
$string['reportentries'] = 'Vendas';
$string['reportexternalid'] = 'Transação no gateway';
$string['reportgateway'] = 'Meio de pagamento';
$string['reportgross'] = 'Bruto';
$string['reportlastpayment'] = 'Último pagamento';
$string['reportnetnotice'] = 'A taxa do Mercado Pago não aparece aqui porque não nos é informada: ela varia por meio de pagamento e por prazo de recebimento, e é descontada do lado deles antes da comissão da plataforma. Seu valor líquido é o do extrato do Mercado Pago.';
$string['reportnocourses'] = 'Nenhum curso foi vendido ainda.';
$string['reportnosales'] = 'Nenhuma venda aprovada neste período.';
$string['reportnostudents'] = 'Ninguém tem acesso às ofertas desta empresa ainda.';
$string['reportnosubs'] = 'Nenhuma oferta de assinatura, ou ninguém assinou ainda.';
$string['reportpaymentmethod'] = 'Forma de pagamento';
$string['reportpayments'] = 'Pagamentos';
$string['reportsales'] = 'Vendas aprovadas';
$string['reportsaleswith'] = 'Vendas que o incluem';
$string['reportsection'] = 'Vendas';
$string['reportstudentsactive'] = 'Alunos com acesso vigente';
$string['reportstudentsince'] = 'Desde';
$string['reportstudentsnotice'] = 'Uma linha por direito de acesso, então um aluno que comprou três ofertas aparece três vezes. A contagem acima é de pessoas distintas. A lista sai dos direitos de acesso e não das vendas, então quem pegou uma oferta gratuita conta como aluno também.';
$string['reportstudentsrows'] = 'Direitos de acesso';
$string['reportsubactive'] = 'Vigente';
$string['reportsubcancelled'] = 'Cancelada';
$string['reportsubduesoon'] = 'Vence em {$a} d';
$string['reportsubexpired'] = 'Vencida';
$string['reportsubnorenew'] = 'renovação cancelada';
$string['reportsubsnotice'] = 'Não há cronograma de cobrança a exibir: o Mercado Pago não tem pagamento recorrente com split, então cada renovação é uma compra separada que estende o acesso. Esta tela mostra quantas vezes cada aluno pagou e por quanto tempo o acesso dele ainda vale.';
$string['reportviewcourses'] = 'Cursos vendidos';
$string['reportviewstudents'] = 'Alunos';
$string['reportviewsubscriptions'] = 'Assinaturas';
$string['reportviewtransactions'] = 'Transações';
$string['resenddone'] = 'A fatura foi reenviada ao aluno.';
$string['resendfailed'] = 'Nada foi enviado — o aluno pode estar suspenso, ou a oferta saiu de venda.';
$string['resendinvoice'] = 'Reenviar fatura';
$string['selectplan'] = 'Selecionar plano';
$string['sellerrole'] = 'Vendedor da empresa';
$string['sellerroledesc'] = 'Monta e publica cursos por uma empresa. Não alcança a conta de pagamento nem a lista de membros, e não pode enviar arquivos: os vídeos do curso precisam ser hospedados fora da plataforma.';
$string['settings'] = 'Configurações';
$string['sortby'] = 'Ordenar por';
$string['sortmanual'] = 'Destaques';
$string['sortname'] = 'Nome';
$string['sortnewest'] = 'Lançamentos';
$string['sortprice'] = 'Preço: menor primeiro';
$string['sortpricedesc'] = 'Preço: maior primeiro';
$string['statusactive'] = 'Ativa';
$string['statusarchived'] = 'Arquivada';
$string['statusdraft'] = 'Rascunho';
$string['statuspublished'] = 'Publicada';
$string['statussuspended'] = 'Suspensa';
$string['switchtocard'] = 'Trocar para cartão';
$string['tasknotifyexpiring'] = 'Avisar alunos sobre acesso prestes a vencer';
$string['typebundle'] = 'Combo';
$string['typecatalog'] = 'Catálogo completo';
$string['typesingle'] = 'Curso único';
$string['unavailable'] = 'Ainda não disponível para compra.';
$string['viewstorefront'] = 'Ver vitrine';
