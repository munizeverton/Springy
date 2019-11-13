## Instalação e configuração

Faça um clone do projeto

`git clone https://github.com/munizeverton/Springy`

O container da aplicação pode ser levantado com o comando abaixo:

`cd docker/ && docker-compose start`

Crie a tabela tests

```
create table tests
(
    id int auto_increment primary key,
    name varchar(255) null,
    created_at datetime null,
    updated_at datetime default now() null,
    deleted tinyint default 0 null
);
```


Após isso a aplicação está disponível em `http://localhost`
