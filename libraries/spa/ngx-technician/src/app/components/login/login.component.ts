import { Component, OnInit } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { apiEndPoint, apiEndPointBase } from '../../../environments/environment';
import { Router } from '@angular/router';
import { BsModalRef } from 'ngx-bootstrap/modal';

@Component({
  selector: 'app-login',
  templateUrl: './login.component.html',
  styleUrls: ['./login.component.scss']
})
export class LoginComponent implements OnInit {
  email: string;
  password: string;
  message: string;
  loading = false;

  constructor(
    private http: HttpClient,
    private router: Router,
  ) { }

  ngOnInit() {
  }

  login() {
    this.loading = true;
    this.http.post<any>(apiEndPoint + apiEndPointBase + '/login_check', {
      email: this.email,
      password: this.password
    })
    .subscribe(res => {
      localStorage.setItem('token', res.token);
      localStorage.setItem('refresh_token', res.refresh_token);
      this.router.navigateByUrl('/');
    }, error => {
      this.message = 'Invalid email or password!';
      this.loading = false;
    });
  }
}
