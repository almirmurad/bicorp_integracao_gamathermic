## BICORP INTEGRAÇÃO GAMATERMIC

- Sistema desenvolivdo em MVC PHP com Banco de dados MYSQL. 
- Recebe o cadastro do cliente do Ploomes CRM e envia ao OMIE ERP, bem como cadastro do cliente no OMIE ERP e envia ao Ploomes CRM.
- Recebe um webhook com um card de proposta ganha no sistema Ploomes CRM e grava um pedido de venda em um dos aplicativos do cliente no sistema Omie ERP.
- Utiliza a API de ambas as plataformas para enviar um novo pedido de venda a um dos aplicativos da plataforma Omie ERP. 
- Sistema Omie ERP através de sua API devolve ao Bicorp Integração uma notificação informando que o pedido foi criado com sucesso. Em seguida o Bicorp Integração envia ao Ploomes CRM via API a notifcação com o número do pedido e salva no historico de interações entre os usuários.
