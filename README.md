# Meu Planejamento Financeiro

## Backup do banco

O PostgreSQL usado pelo app roda no Docker Compose e seus dados ficam no volume
persistente `financeiro_postgres_data`. Esse volume sobrevive a reinicializacoes
dos containers, mas nao protege contra perda do disco ou exclusao do volume.
Mantenha backups fora do container e, idealmente, copie-os tambem para outro
disco ou local seguro.

No PowerShell, na pasta do projeto, crie e valide um backup com:

```powershell
.\scripts\backup-database.ps1
```

O arquivo `.dump` e salvo em `backups\`, e o script verifica sua integridade.
Essa pasta esta excluida do Git porque o backup contem todos os dados financeiros.
O comando requer que o WSL e o Docker estejam em execucao.

Para automatizar, crie uma tarefa diaria no Agendador de Tarefas do Windows:

- Programa: `powershell.exe`
- Argumentos: `-NoProfile -ExecutionPolicy Bypass -File "D:\dev\projetos\financeiro\scripts\backup-database.ps1"`
- Iniciar em: `D:\dev\projetos\financeiro`

Guarde uma copia dos arquivos de backup fora deste computador. Antes de restaurar,
preserve o banco atual; restaurar sobre o banco ativo pode substituir os dados
existentes.
