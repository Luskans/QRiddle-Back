<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Http\JsonResponse; // Pour typer le retour
use Illuminate\Http\Response; // Pour les codes de statut HTTP
use Illuminate\Support\Facades\Log;

class AuthController extends Controller
{
    /**
     * Enregistre un nouvel utilisateur.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function register(Request $request): JsonResponse
    {
        // Validation des données d'entrée
        // Note: 'username' dans la validation mais 'name' dans le modèle User. Assurons la cohérence.
        //       Si le champ s'appelle 'name' dans la DB, utilisons 'name' ici aussi.
        $validated = $request->validate([
            'name' => 'required|string|max:255', // Changé de 'username' à 'name' pour correspondre au modèle User
            'email' => 'required|string|email|max:255|unique:users,email', // Ajout max et spécification table/colonne pour unique
            'password' => [
                'required',
                'confirmed', // Vérifie que 'password_confirmation' correspond
                Password::min(12)
                    ->mixedCase() // Au moins une majuscule et une minuscule
                    ->letters()   // Au moins une lettre
                    ->numbers()   // Au moins un chiffre
                    ->symbols(),  // Au moins un symbole
            ],
            // Assurez-vous que le frontend envoie bien 'password_confirmation'
            'password_confirmation' => 'required',
        ]);

        try {
            // Création de l'utilisateur
            $user = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => Hash::make($validated['password']),
                // 'image' utilisera la valeur par défaut définie dans le modèle User
            ]);

            // Optionnel: Envoyer un email de vérification ici si nécessaire
            // if ($user instanceof \Illuminate\Contracts\Auth\MustVerifyEmail) {
            //     $user->sendEmailVerificationNotification();
            // }

            // Création du token d'authentification pour une connexion immédiate (optionnel)
            // Si l'utilisateur doit vérifier son email, ne pas générer de token ici.
            // $token = $user->createToken('auth_token')->plainTextToken;

            // Retourner une réponse de succès
            // On ne retourne PAS le token ici si la vérification d'email est requise.
            // On peut retourner l'utilisateur créé pour info, mais sans le token.
            return response()->json([
                'message' => 'User registered successfully. Please check your email for verification.', // Adapter le message
                'user' => $user->only(['id', 'name', 'email', 'image']) // Retourner seulement les infos non sensibles
            ], Response::HTTP_CREATED); // 201 Created

            // Si connexion immédiate après register (moins courant avec vérification email):
            // return response()->json([
            //     'message' => 'User registered and logged in successfully.',
            //     'user' => $user->only(['id', 'name', 'email', 'image']),
            //     'token' => $token
            // ], Response::HTTP_CREATED);

        } catch (\Exception $e) {
            Log::error('User registration failed: ' . $e->getMessage());
            return response()->json(['message' => 'Registration failed. Please try again later.'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Connecte un utilisateur existant.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function login(Request $request): JsonResponse
    {
        // Validation des données d'entrée
        $validated = $request->validate([
            'email' => 'required|string|email',
            'password' => 'required|string',
            'device_name' => 'sometimes|string|max:255' // Optionnel: nom de l'appareil pour le token
        ]);

        // Tentative de connexion
        if (!Auth::attempt(['email' => $validated['email'], 'password' => $validated['password']])) {
            // Échec de l'authentification
            return response()->json(['message' => 'Invalid credentials.'], Response::HTTP_UNAUTHORIZED); // 401 Unauthorized
        }

        // Authentification réussie
        /** @var \App\Models\User $user */
        $user = Auth::user();

        // Optionnel: Vérifier si l'email est vérifié (si requis)
        // if ($user instanceof \Illuminate\Contracts\Auth\MustVerifyEmail && !$user->hasVerifiedEmail()) {
        //     Auth::logout(); // Déconnecter si email non vérifié
        //     return response()->json(['message' => 'Email not verified. Please check your email.'], Response::HTTP_FORBIDDEN); // 403 Forbidden
        // }

        // Création du token d'authentification
        $deviceName = $validated['device_name'] ?? 'mobileApp'; // Nom par défaut si non fourni
        $token = $user->createToken($deviceName)->plainTextToken;

        // Retourner une réponse de succès avec l'utilisateur et le token
        return response()->json([
            'message' => 'Login successful.',
            'user' => $user->only(['id', 'name', 'email', 'image', 'email_verified_at']), // Retourner les infos utiles
            'token' => $token
        ], Response::HTTP_OK); // 200 OK
    }

    /**
     * Déconnecte l'utilisateur authentifié (invalide le token courant).
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function logout(Request $request): JsonResponse
    {
        try {
            // Invalide le token spécifique utilisé pour faire la requête
            $request->user()->currentAccessToken()->delete();

            return response()->json(['message' => 'Successfully logged out.'], Response::HTTP_OK);

        } catch (\Exception $e) {
            Log::error('User logout failed: ' . $e->getMessage());
            // Même en cas d'erreur (ex: token déjà invalide), on renvoie succès car l'utilisateur est déconnecté côté client
            return response()->json(['message' => 'Logout processed.'], Response::HTTP_OK);
        }

        /* Alternative: Supprimer TOUS les tokens de l'utilisateur (déconnexion de partout)
           Très rarement ce qu'on veut pour une simple déconnexion mobile.
           $request->user()->tokens()->delete();
           return response()->json(['message' => 'Successfully logged out from all devices.'], Response::HTTP_OK);
        */
    }

    /**
     * Récupère les informations de l'utilisateur actuellement authentifié.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function user(Request $request): JsonResponse
    {
        // Le middleware 'auth:sanctum' s'assure que $request->user() est disponible
        /** @var \App\Models\User $user */
        $user = $request->user();

        // Retourner les informations de l'utilisateur (sélectionner les champs si nécessaire)
        return response()->json(
            $user->only(['id', 'name', 'email', 'image', 'email_verified_at']) // Exclure mot de passe, etc.
        , Response::HTTP_OK);
    }

    // --- Méthodes Optionnelles (Mot de passe oublié, Vérification Email) ---

    // public function sendPasswordResetLink(Request $request)
    // {
    //     // Logique pour envoyer le lien de réinitialisation
    // }

    // public function resetPassword(Request $request)
    // {
    //     // Logique pour réinitialiser le mot de passe avec le token
    // }

    // public function verifyEmail(Request $request, $id, $hash)
    // {
    //     // Logique pour marquer l'email comme vérifié
    // }

    // public function resendVerificationEmail(Request $request)
    // {
    //     // Logique pour renvoyer l'email de vérification
    // }
}