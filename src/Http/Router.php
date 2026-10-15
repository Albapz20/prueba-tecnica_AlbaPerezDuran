<?php
declare(strict_types=1);

namespace ReservasAlba\Http;

use ReservasAlba\Exception\HttpException;
use ReservasAlba\Exception\NotFoundException;

// Decide qué código ejecutar según el método y la ruta de la petición.
 
final class Router
{
    // Lista de rutas registradas.
    private array $rutas = [];

    // Registra una ruta.
     
    public function agregar(string $metodo, string $patron, callable $manejador): void
    {
        $regex = '#^' . preg_replace('#\{(\w+)\}#', '(?P<$1>[^/]+)', $patron) . '$#';
        $this->rutas[] = [$metodo, $regex, $manejador];
    }

    // Busca la ruta que coincide y la ejecuta. Si no hay ninguna, lanza un error. 
    public function despachar(Request $request): void
    {
        $rutaExiste = false;  

        foreach ($this->rutas as [$metodo, $regex, $manejador]) {
            if (!preg_match($regex, $request->ruta, $coincidencias)) {
                continue;   
            }

            $rutaExiste = true;

            if ($metodo === $request->metodo) {
                $parametros = array_filter($coincidencias, 'is_string', ARRAY_FILTER_USE_KEY);
                $manejador($request, $parametros);
                return;
            }
        }

        if ($rutaExiste) {
            throw new HttpException(405, 'Método no permitido.');
        }
        throw new NotFoundException('Ruta no encontrada.');
    }
}