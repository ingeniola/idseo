# Servidores MCP del proyecto

`.mcp.json` (en la raíz) define los servidores MCP que Claude Code carga al
abrir este repositorio. Al entrar por primera vez, Claude Code pide aprobar
los servidores del proyecto.

## emcp-webs27-online

Instancia de eMCP Tools (Elementor/WordPress) sobre `webs27.online/prueba2`.

- Transporte: HTTP
- URL: `https://webs27.online/prueba2/index.php?rest_route=/mcp/emcp-tools-server`
- Autenticación: cabecera `Authorization` con Basic auth
  (usuario de WordPress + *application password*)

La credencial **no** se guarda en el repositorio: `.mcp.json` referencia la
variable de entorno `EMCP_WEBS27_ONLINE_AUTH`, que Claude Code expande al
arrancar. Hay que exportarla en el shell desde el que se lanza `claude`:

```bash
export EMCP_WEBS27_ONLINE_AUTH='Basic <base64 de usuario:application-password>'
```

Para que persista, añadirla a `~/.zshrc` / `~/.bashrc` (o al gestor de
secretos que uses). Si la variable no está definida, el servidor falla al
conectar con un 401.

Comprobación rápida:

```bash
claude mcp list          # debe mostrar emcp-webs27-online conectado
```
