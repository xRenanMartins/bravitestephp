import { HttpClient, HttpParams } from '@angular/common/http';
import { Injectable } from '@angular/core';
import { Observable } from 'rxjs';
import { environment } from 'src/environments/environment';

@Injectable({
  providedIn: 'root'
})
export class ContactService {
  base_url: string = `/contact`;
  
  constructor(private httpClient: HttpClient) {}

  prepareParams(paramsObj: any): HttpParams {
    let searchParams = new HttpParams();
    for (let key in paramsObj) {
      if (paramsObj.hasOwnProperty(key)) {
        searchParams = searchParams.append(key, paramsObj[key]);
      }
    }
    return searchParams;
  }

  get(payload: any): Observable<any[]> {
    return this.httpClient.get<any[]>(`${environment.url_ms}`+this.base_url, {
      params: this.prepareParams(payload),
    });
  }

  create(payload: any): Observable<any> {
    return this.httpClient.post<any>(`${environment.url_ms}`+this.base_url, payload);
  }
  
  update(payload: any): Observable<any> {
  return this.httpClient.put<any>(
    `${environment.url_ms}${this.base_url}/${payload.id}/update`, payload
    );
  }

  delete(id: any): Observable<any> {
    return this.httpClient.delete<any>(`${environment.url_ms}${this.base_url}/${id}/delete`);
  }
}
