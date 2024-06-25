<?php

declare(strict_types=1);

namespace App\GraphQL\Mutations;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class AuthMutation
{
    /**
     * @param  null  $_
     * @param  array{}  $args
     */
    public function __invoke($_, array $args)
    {
        // TODO implement the resolver
    }

    /**
     * Login
     *
     * @param  null  $_
     * @param  array{}  $args
     */
    public function login($_, array $args): string
    {
        $user = User::query()
            ->where('email', $args['input']['email'])
            ->first();

        // user not found
        if (! $user) {
            throw ValidationException::withMessages([
                'login' => ['User not found!.'],
            ]);
        }

        // password not match
        if (! Hash::check($args['input']['password'], $user->password)) {
            throw ValidationException::withMessages([
                'login' => ['Credentials are incorrect.'],
            ]);
        }

        // user not active
        if ($user->status != 'active') {
            throw ValidationException::withMessages([
                'login' => ['User is not active, status is '.$user->status],
            ]);
        }

        if ($user->deactivated()) {
            throw ValidationException::withMessages([
                'login' => ['Your account is deactivated because of self exclusion.'],
            ]);
        }

        // create the token
        return $user->createToken('web')->plainTextToken;
    }

    /**
     * Me
     *
     * @param  null  $_
     * @param  array{}  $args
     */
    public function me($_, array $args)
    {
        // TODO implement the resolver
    }

    /**
     * Register
     *
     * @param  null  $_
     * @param  array{}  $args
     */
    public function register($_, array $args): bool
    {
        // validate the input
        $validated = validator($args['input'], [
            'email' => 'required|email|unique:users,email',
            'password' => 'required|min:8',
            'country' => 'required',
            'currency' => 'required',
        ])->validate();

        // create the user
        $user = User::create([
            'email' => $validated['email'],
            'username' => $this->generateUniqueUsername(),
            'password' => Hash::make($validated['password']),
            'country' => $validated['country'],
            'currency' => $validated['currency'],
        ]);

        if ($user->wasRecentlyCreated) {
            $brand = getMainDomainPart(request()->getHost());
            // send the verification email
            $user->sendEmailVerificationNotification($user, $brand);

            return true;
        }

        return false;
    }

    /**
     * Logout
     *
     * @param  null  $_
     * @param  array{}  $args
     */
    public function logout($_, array $args): bool
    {
        if (! Auth::check()) {
            // You can throw an exception or return false based on your preference
            throw ValidationException::withMessages([
                'logout' => ['Not authenticated.'],
            ]);
        }
        // invalidate the token
        if (auth()->user()->currentAccessToken()->delete()) {
            return true;
        }

        return false;

    }

    /**
     * Forgot password
     *
     * @param  null  $_
     * @param  array{}  $args
     */
    public function forgotPassword($_, array $args): bool
    {
        $email = $args['input']['email'];
        $user = User::query()
            ->where('email', $email)
            ->first();

        if (! $user) {
            return false;
        }

        $token = Str::uuid();

        $reset = DB::table('password_resets')->where('email', $email)->first();
        if ($reset) {
            DB::table('password_resets')->where('email', $email)->update([
                'token' => $token,
                'created_at' => Carbon::now(),
            ]);
        } else {
            DB::table('password_resets')->insert([
                'email' => $email,
                'token' => $token,
                'created_at' => Carbon::now(),
            ]);
        }

        // $user->notify(new PasswordResetNotification($token, $user));

        return true;
    }

    /**
     * Reset password
     *
     * @param  null  $_
     * @param  array{}  $args
     * @return mixed
     */
    public function resetPassword($_, array $args): bool
    {
        /*
        * Here we will attempt to reset the user's password. If it is successful we
        * will update the password on an actual user model and persist it to the
        * database. Otherwise, we will parse the error and return the response.
        */

        $email = $args['input']['email'];
        $token = $args['input']['token'];
        $password = $args['input']['password'];

        $reset = DB::table('password_resets')
            ->where('token', $token)
            ->where('email', $email)
            ->first();

        if (! $reset) {
            return false;
        }

        // delete the token
        DB::table('password_resets')->where('email', $email)->delete();

        // update the password
        $user = User::query()
            ->where('email', $email)
            ->first();

        $user->password = bcrypt($password);
        $user->update();

        /*
        * Delete All API tokens of the user
        */
        $user->tokens()->delete();  // Revoke all tokens...

        return true;
    }

    /**
     * generate a unique username
     */
    private function generateUniqueUsername(): string
    {
        // Generate a random length between 8 and 12
        $length = rand(8, 12);

        // Generate a random string of the determined length
        $username = Str::lower(Str::random($length));

        // Check if the username exists in the User model
        $user = User::query()
            ->where('username', $username)
            ->first();
        if ($user) {
            return $this->generateUniqueUsername(); // Recursively call until a unique username is found
        }

        return $username;
    }
}
