<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class AuthController extends Controller
{
    // REGISTRO PÚBLICO: siempre crea empleado
    public function register(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users,email',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'role' => 'empleado',
            'active' => true,
        ]);

        return response()->json([
            'message' => 'Usuario registrado correctamente',
            'user' => $user,
        ], 201);
    }

    // LOGIN
    public function login(Request $request)
    {
        $data = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        $user = User::where('email', $data['email'])->first();

        if (!$user || !Hash::check($data['password'], $user->password)) {
            return response()->json([
                'message' => 'Correo o contraseña incorrectos',
            ], 401);
        }

        if (!$user->active) {
            return response()->json([
                'message' => 'Tu usuario está desactivado',
            ], 403);
        }

        $user->tokens()->delete();

        $token = $user->createToken('lavanderia')->plainTextToken;

        return response()->json([
            'message' => 'Inicio de sesión correcto',
            'token' => $token,
            'user' => $user,
        ]);
    }

    // USUARIO AUTENTICADO
    public function me(Request $request)
    {
        return response()->json($request->user());
    }

    // CERRAR SESIÓN
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Sesión cerrada correctamente',
        ]);
    }

    // LISTA DE USUARIOS: SOLO ADMINISTRADOR
    public function index()
    {
        return User::select(
            'id',
            'name',
            'email',
            'role',
            'active',
            'created_at'
        )->orderBy('id', 'desc')->get();
    }

    // CREAR USUARIO: SOLO ADMINISTRADOR
    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users,email',
            'password' => 'required|string|min:8|confirmed',
            'role' => ['required', Rule::in(['administrador', 'empleado'])],
        ]);

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'role' => $data['role'],
            'active' => true,
        ]);

        return response()->json([
            'message' => 'Usuario creado correctamente',
            'user' => $user,
        ], 201);
    }

    // ACTIVAR / DESACTIVAR USUARIO: SOLO ADMINISTRADOR
    public function toggleActive(User $user)
    {
        $user->active = !$user->active;
        $user->save();

        return response()->json([
            'message' => $user->active
                ? 'Usuario activado'
                : 'Usuario desactivado',
            'user' => $user,
        ]);
    }
}