# Servidores MCP del proyecto

`.mcp.json` (en la raíz) define los servidores MCP que Claude Code carga al
abrir este repositorio, tanto en local como en sesiones remotas
(Claude Code on the web).

## emcp-webs27-online

Instancia de eMCP Tools (Elementor/WordPress) sobre `webs27.online/prueba2`.

- Transporte: HTTP
- URL: `https://webs27.online/prueba2/index.php?rest_route=/mcp/emcp-tools-server`
- Autenticación: cabecera `Authorization` con Basic auth
  (usuario de WordPress + *application password*)

La credencial **no** se guarda en el repositorio: `.mcp.json` referencia la
variable de entorno `EMCP_WEBS27_ONLINE_AUTH`, que Claude Code expande al
arrancar.

### En sesiones remotas (Claude Code on the web)

Es el caso habitual en este proyecto. Hay que configurar dos cosas en el
*environment* (`Ingenio`), no dentro de la sesión — el contenedor es efímero
y cualquier `export` o cambio en `~/.bashrc` se pierde al cerrarla:

1. **Variable de entorno** `EMCP_WEBS27_ONLINE_AUTH` con el valor
   `Basic <base64 de usuario:application-password>`, en la configuración del
   environment.
2. **Acceso de red a `webs27.online`**. El environment usa una política de
   red restringida (*trusted network access*), que deniega el dominio con un
   403 en el CONNECT del proxy de salida. Hay que permitirlo explícitamente
   en la política de red del environment.

Sin el punto 2, el servidor no conecta aunque la credencial sea correcta.

Documentación de environments y políticas de red:
<https://code.claude.com/docs/en/claude-code-on-the-web>

### En local

Exportar la variable en el shell desde el que se lanza `claude`:

```bash
export EMCP_WEBS27_ONLINE_AUTH='Basic <base64 de usuario:application-password>'
```

Para que persista, añadirla a `~/.zshrc` / `~/.bashrc` (o al gestor de
secretos que uses). Si la variable no está definida, el servidor falla al
conectar con un 401.

### Comprobación

```bash
claude mcp list          # debe mostrar emcp-webs27-online conectado
```
