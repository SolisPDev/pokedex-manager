<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GeminiService
{
    protected string $apiKey;
    protected string $baseUrl;

    public function __construct()
    {
        $this->apiKey = env('GEMINI_API_KEY', '');
        $this->baseUrl = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent';
    }

    /**
     * Identify a Pokemon from a base64 encoded image.
     */
    public function identifyPokemon(string $base64Image, string $mimeType = 'image/jpeg'): array
    {
        if (empty($this->apiKey) || $this->apiKey === 'tu_api_key_aqui') {
            return [
                'name' => 'Desconocido',
                'confidence' => 0.0,
                'type' => 'desconocido',
                'suggestion' => 'Por favor configura tu GEMINI_API_KEY en el archivo .env para habilitar el reconocimiento por IA.',
            ];
        }

        // Clean base64 data if it contains the data prefix (e.g. data:image/jpeg;base64,...)
        if (preg_match('/^data:([^;]+);base64,(.+)$/', $base64Image, $matches)) {
            $mimeType = $matches[1];
            $base64Image = $matches[2];
        }

        try {
            $response = Http::post("{$this->baseUrl}?key={$this->apiKey}", [
                'contents' => [
                    [
                        'parts' => [
                            [
                                'text' => "Identifica este Pokémon a partir de la imagen proporcionada. Devuelve únicamente un JSON válido con las siguientes claves: 'name' (nombre del Pokémon en minúsculas y formato correcto de PokéAPI), 'confidence' (un flotante de 0.0 a 1.0 representando la confianza), 'type' (el tipo o tipos separados por coma, ej. 'fire, flying'), 'suggestion' (una sugerencia corta de 1 o 2 líneas en español explicando por qué agregarlo o cómo usarlo en batallas). Asegúrate de no incluir markdown o texto adicional fuera del JSON."
                            ],
                            [
                                'inlineData' => [
                                    'mimeType' => $mimeType,
                                    'data' => $base64Image,
                                ]
                            ]
                        ]
                    ]
                ],
                'generationConfig' => [
                    'responseMimeType' => 'application/json'
                ]
            ]);

            if ($response->successful()) {
                $resultText = $response->json()['candidates'][0]['content']['parts'][0]['text'] ?? '{}';
                return json_decode(trim($resultText), true) ?? [];
            } else {
                Log::error('Gemini API Error: ' . $response->body());
            }
        } catch (\Exception $e) {
            Log::error('Gemini Service Exception: ' . $e->getMessage());
        }

        return [
            'name' => 'Error',
            'confidence' => 0.0,
            'type' => 'error',
            'suggestion' => 'Hubo un problema al procesar la imagen con la API de Gemini.',
        ];
    }

    /**
     * Get contextual insights based on the user's collection of Pokemon.
     */
    public function getCollectionInsights(array $collection): string
    {
        if (empty($this->apiKey) || $this->apiKey === 'tu_api_key_aqui') {
            return 'Por favor configura tu GEMINI_API_KEY en el archivo .env para habilitar los consejos del asistente de IA.';
        }

        if (empty($collection)) {
            return '¡Tu colección está vacía! Comienza agregando algunos Pokémon favoritos y vuelve a preguntarme para darte consejos estratégicos de equipo.';
        }

        // Format the collection data for the prompt
        $formattedList = array_map(function ($item) {
            return "- {$item['pokemon_name']} (Tipos: {$item['pokemon_type']}) - Notas: " . ($item['custom_notes'] ?? 'Ninguna');
        }, $collection);
        $pokemonListString = implode("\n", $formattedList);

        $prompt = "Actúa como un Profesor Pokémon y experto estratega de batallas. El usuario tiene los siguientes Pokémon en su colección personal:\n\n" .
            $pokemonListString . "\n\n" .
            "Analiza detalladamente esta colección. Proporciona recomendaciones estratégicas y divertidas sobre el balance de tipos, fortalezas, debilidades, y qué tipos de Pokémon le convendría buscar a continuación para complementar su equipo. Escribe tu respuesta en un tono amigable, entusiasta y en español. Mantén el texto corto (máximo 3 párrafos).";

        try {
            $response = Http::post("{$this->baseUrl}?key={$this->apiKey}", [
                'contents' => [
                    [
                        'parts' => [
                            ['text' => $prompt]
                        ]
                    ]
                ]
            ]);

            if ($response->successful()) {
                return $response->json()['candidates'][0]['content']['parts'][0]['text'] ?? 'No se pudo generar respuesta.';
            } else {
                Log::error('Gemini API Insights Error: ' . $response->body());
            }
        } catch (\Exception $e) {
            Log::error('Gemini Service Insights Exception: ' . $e->getMessage());
        }

        return 'Lo siento, no pude contactar al Profesor Pokémon en este momento. Revisa tus logs.';
    }
}
